<?php

namespace App\Services\Payment;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OutboxEvent;
use App\Models\PaymentEvent;
use App\Models\PaymentIntent;
use App\Models\ReconciliationDiscrepancy;
use App\Models\Refund;
use App\Models\RefundEvent;
use App\Services\Accounting\LedgerService;
use App\Services\Fulfillment\FulfillmentOrchestrator;
use App\Services\Notification\NotificationService;
use App\Services\Order\OrderStateMachine;
use App\Services\Security\RedactionService;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentService
{
    public function __construct(
        private readonly PaymentGatewayManager $gatewayManager,
        private readonly OrderStateMachine $orderStateMachine,
        private readonly LedgerService $ledgerService,
        private readonly NotificationService $notifications,
        private readonly RedactionService $redaction,
        private readonly FulfillmentOrchestrator $fulfillmentOrchestrator,
    ) {}

    public function createPaymentSession(Order $order, ?string $provider = null): PaymentIntent
    {
        if (! in_array($order->status, [OrderStatus::AWAITING_PAYMENT, OrderStatus::PAYMENT_VERIFYING], true)) {
            throw new DomainException("Order [{$order->order_number}] is not eligible for payment in state [{$order->status->value}].");
        }

        if ($order->expires_at && $order->expires_at->isPast()) {
            $this->orderStateMachine->transition($order, OrderStatus::EXPIRED, 'system', null, 'Order payment window expired.');
            throw new DomainException('Order payment window has expired.');
        }

        $gateway = $this->gatewayManager->resolve($provider);

        // Reuse active pending intent for same order & provider
        $existing = PaymentIntent::query()
            ->where('order_id', $order->id)
            ->where('provider', $gateway->providerCode())
            ->whereIn('status', [PaymentStatus::PENDING->value, PaymentStatus::VERIFYING->value])
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if ($existing) {
            return $existing;
        }

        $idempotencyKey = "pay:{$order->id}:{$gateway->providerCode()}:".Str::uuid();
        $session = $gateway->createSession($order, $idempotencyKey);

        return DB::transaction(function () use ($order, $gateway, $idempotencyKey, $session): PaymentIntent {
            $intent = PaymentIntent::query()->create([
                'order_id' => $order->id,
                'provider' => $gateway->providerCode(),
                'provider_mode' => $gateway->providerMode(),
                'provider_reference' => $session['provider_reference'],
                'merchant_reference' => $session['merchant_reference'],
                'idempotency_key' => $idempotencyKey,
                'status' => PaymentStatus::PENDING,
                'currency' => $order->currency,
                'amount_minor' => $order->total_minor,
                'qr_payload' => $session['qr_payload'],
                'checkout_url' => $session['checkout_url'],
                'expires_at' => $session['expires_at'],
                'provider_metadata' => $session['metadata'],
            ]);

            PaymentEvent::query()->create([
                'payment_intent_id' => $intent->id,
                'event_type' => 'intent_created',
                'external_event_id' => 'init:'.$intent->id,
                'from_status' => null,
                'to_status' => PaymentStatus::PENDING->value,
                'signature_valid' => true,
                'payload_redacted' => $this->redaction->redact($session['metadata']),
                'created_at' => now(),
            ]);

            return $intent;
        });
    }

    /**
     * Process payment gateway webhook safely:
     * - Reject invalid signatures
     * - Deduplicate replayed events
     * - Match amount, currency, merchant identity
     * - Verify server-to-server before marking paid
     * - Handle late payments on expired orders safely (needs_review + discrepancy)
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     * @return array{accepted: bool, duplicate: bool, status: string, message: string}
     */
    public function handleWebhook(string $provider, array $payload, array $headers, string $rawBody): array
    {
        $gateway = $this->gatewayManager->resolve($provider);
        $parsed = $gateway->parseAndVerifyWebhook($payload, $headers, $rawBody);

        if (! $parsed['signature_valid']) {
            throw new DomainException('Invalid payment webhook signature.');
        }

        if ($parsed['provider_reference'] === '') {
            throw new DomainException('Webhook missing provider reference.');
        }

        return DB::transaction(function () use ($gateway, $parsed, $payload): array {
            /** @var PaymentIntent|null $intent */
            $intent = PaymentIntent::query()
                ->where('provider', $gateway->providerCode())
                ->where('provider_reference', $parsed['provider_reference'])
                ->lockForUpdate()
                ->first();

            if (! $intent) {
                throw new DomainException("Unknown payment reference [{$parsed['provider_reference']}].");
            }

            // Duplicate / replay protection
            $alreadyProcessed = PaymentEvent::query()
                ->where('payment_intent_id', $intent->id)
                ->where('external_event_id', $parsed['external_event_id'])
                ->exists();

            if ($alreadyProcessed) {
                return [
                    'accepted' => true,
                    'duplicate' => true,
                    'status' => $intent->status->value,
                    'message' => 'Duplicate webhook ignored idempotently.',
                ];
            }

            /** @var Order $order */
            $order = Order::query()->lockForUpdate()->findOrFail($intent->order_id);

            // Already paid? Record duplicate event safely and do NOT re-trigger fulfillment.
            if ($intent->status === PaymentStatus::PAID) {
                PaymentEvent::query()->create([
                    'payment_intent_id' => $intent->id,
                    'event_type' => 'duplicate_webhook_after_paid',
                    'external_event_id' => $parsed['external_event_id'],
                    'from_status' => $intent->status->value,
                    'to_status' => $intent->status->value,
                    'signature_valid' => true,
                    'payload_redacted' => $this->redaction->redact($payload),
                    'created_at' => now(),
                ]);

                return [
                    'accepted' => true,
                    'duplicate' => true,
                    'status' => PaymentStatus::PAID->value,
                    'message' => 'Payment already verified; duplicate callback recorded.',
                ];
            }

            // For mock simulator, persist simulated outcome so server-to-server verify reflects it
            if ($gateway->providerMode() === 'mock') {
                $meta = $intent->provider_metadata ?? [];
                $meta['simulated_status'] = $parsed['status'];
                $meta['simulated_amount_minor'] = $parsed['amount_minor'];
                $meta['simulated_currency'] = $parsed['currency'];
                $intent->provider_metadata = $meta;
                $intent->save();
            }

            if ($parsed['status'] === 'paid') {
                // Server-to-server verification before trusting webhook
                $verification = $gateway->verifyTransaction($intent);

                $amountMatches = $verification['amount_minor'] === $intent->amount_minor
                    && $parsed['amount_minor'] === $intent->amount_minor;
                $currencyMatches = strtoupper($verification['currency']) === strtoupper($intent->currency)
                    && strtoupper($parsed['currency']) === strtoupper($intent->currency);

                if (! $verification['verified'] || ! $amountMatches || ! $currencyMatches) {
                    $this->flagPaymentMismatch($order, $intent, $parsed, $verification);

                    return [
                        'accepted' => true,
                        'duplicate' => false,
                        'status' => OrderStatus::NEEDS_REVIEW->value,
                        'message' => 'Payment amount/currency/verification mismatch flagged for review.',
                    ];
                }

                // Handle late payment after order expiration safely
                $isLatePayment = $order->status === OrderStatus::EXPIRED
                    || ($order->expires_at && $order->expires_at->isPast() && $order->status === OrderStatus::AWAITING_PAYMENT);

                $fromStatus = $intent->status->value;
                $intent->status = PaymentStatus::PAID;
                $intent->paid_amount_minor = $verification['amount_minor'];
                $intent->verified_at = now();
                $intent->save();

                PaymentEvent::query()->create([
                    'payment_intent_id' => $intent->id,
                    'event_type' => $isLatePayment ? 'late_payment_verified' : 'payment_verified',
                    'external_event_id' => $parsed['external_event_id'],
                    'from_status' => $fromStatus,
                    'to_status' => PaymentStatus::PAID->value,
                    'signature_valid' => true,
                    'payload_redacted' => $this->redaction->redact($payload),
                    'created_at' => now(),
                ]);

                $order->payment_status = PaymentStatus::PAID;
                $order->save();

                $this->postPaymentLedgerEntries($order, $intent);

                if ($isLatePayment) {
                    if ($order->status->canTransitionTo(OrderStatus::NEEDS_REVIEW)) {
                        $this->orderStateMachine->transition($order, OrderStatus::NEEDS_REVIEW, 'webhook', null, 'Late payment received after expiration.');
                    }
                    ReconciliationDiscrepancy::query()->create([
                        'order_id' => $order->id,
                        'classification' => 'paid_not_fulfilled',
                        'severity' => 'high',
                        'resolution_status' => 'open',
                        'summary' => "Late payment verified for expired order {$order->order_number}. Requires review or refund.",
                        'detected_at' => now(),
                        'audit_history' => [['event' => 'detected_late_payment', 'at' => now()->toIso8601String()]],
                    ]);
                    $this->notifications->notifyOrderEvent($order, 'order_requires_support');

                    return [
                        'accepted' => true,
                        'duplicate' => false,
                        'status' => 'needs_review',
                        'message' => 'Late payment verified and placed in safe review.',
                    ];
                }

                $this->orderStateMachine->transition($order, OrderStatus::PAID, 'webhook', null, 'Server-to-server payment verified.');
                $this->notifications->notifyOrderEvent($order, 'payment_confirmed');

                // Atomic outbox event + trigger resilient fulfillment orchestrator
                OutboxEvent::query()->firstOrCreate(
                    ['idempotency_key' => "outbox:payment_verified:{$order->id}"],
                    [
                        'aggregate_type' => 'order',
                        'aggregate_id' => $order->id,
                        'event_type' => 'order.payment_verified',
                        'payload' => ['order_id' => $order->id, 'payment_intent_id' => $intent->id],
                        'status' => 'pending',
                        'available_at' => now(),
                    ],
                );

                DB::afterCommit(function () use ($order): void {
                    $this->fulfillmentOrchestrator->initiateForPaidOrder($order->id);
                });

                return [
                    'accepted' => true,
                    'duplicate' => false,
                    'status' => PaymentStatus::PAID->value,
                    'message' => 'Payment verified and fulfillment queued.',
                ];
            }

            // Failed or expired webhook
            $targetPaymentStatus = $parsed['status'] === 'expired' ? PaymentStatus::EXPIRED : PaymentStatus::FAILED;
            $fromStatus = $intent->status->value;
            if ($intent->status->canTransitionTo($targetPaymentStatus)) {
                $intent->status = $targetPaymentStatus;
                $intent->save();
            }

            PaymentEvent::query()->create([
                'payment_intent_id' => $intent->id,
                'event_type' => 'webhook_'.$targetPaymentStatus->value,
                'external_event_id' => $parsed['external_event_id'],
                'from_status' => $fromStatus,
                'to_status' => $intent->status->value,
                'signature_valid' => true,
                'payload_redacted' => $this->redaction->redact($payload),
                'created_at' => now(),
            ]);

            $order->payment_status = $targetPaymentStatus;
            $order->save();

            $targetOrderStatus = $targetPaymentStatus === PaymentStatus::EXPIRED ? OrderStatus::EXPIRED : OrderStatus::FAILED;
            if ($order->status->canTransitionTo($targetOrderStatus)) {
                $this->orderStateMachine->transition($order, $targetOrderStatus, 'webhook', null, "Payment {$targetPaymentStatus->value}.");
            }

            return [
                'accepted' => true,
                'duplicate' => false,
                'status' => $intent->status->value,
                'message' => "Payment marked {$intent->status->value}.",
            ];
        });
    }

    public function requestRefund(Order $order, string $reason, string $adminUserId): Refund
    {
        if ($order->payment_status !== PaymentStatus::PAID) {
            throw new DomainException('Only paid orders can be refunded.');
        }

        /** @var PaymentIntent $intent */
        $intent = $order->paymentIntents()
            ->where('status', PaymentStatus::PAID->value)
            ->firstOrFail();

        $gateway = $this->gatewayManager->resolve($intent->provider);
        $idempotencyKey = "refund:{$order->id}:{$intent->id}";

        return DB::transaction(function () use ($order, $intent, $gateway, $reason, $adminUserId, $idempotencyKey): Refund {
            $existing = Refund::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }

            // Flag discrepancy if refund requested after confirmed fulfillment
            if ($order->fulfillment_status->value === 'succeeded') {
                ReconciliationDiscrepancy::query()->create([
                    'order_id' => $order->id,
                    'classification' => 'refund_after_fulfillment',
                    'severity' => 'critical',
                    'resolution_status' => 'open',
                    'summary' => "Refund requested for order {$order->order_number} after confirmed fulfillment.",
                    'detected_at' => now(),
                    'audit_history' => [['event' => 'refund_after_fulfillment', 'admin_id' => $adminUserId, 'at' => now()->toIso8601String()]],
                ]);
            }

            $result = $gateway->requestRefund($intent, $order->total_minor, $reason, $idempotencyKey);

            $refund = Refund::query()->create([
                'order_id' => $order->id,
                'payment_intent_id' => $intent->id,
                'requested_by_user_id' => $adminUserId,
                'idempotency_key' => $idempotencyKey,
                'provider_refund_reference' => $result['provider_refund_reference'],
                'status' => $result['status'],
                'currency' => $order->currency,
                'amount_minor' => $order->total_minor,
                'reason' => $reason,
                'completed_at' => $result['status'] === 'succeeded' ? now() : null,
            ]);

            RefundEvent::query()->create([
                'refund_id' => $refund->id,
                'from_status' => null,
                'to_status' => $refund->status,
                'payload_redacted' => ['message' => $result['message'], 'supported' => $result['supported']],
                'created_at' => now(),
            ]);

            if ($refund->status === 'succeeded') {
                $intent->status = PaymentStatus::REFUNDED;
                $intent->save();

                $order->payment_status = PaymentStatus::REFUNDED;
                $order->save();

                if ($order->status->canTransitionTo(OrderStatus::REFUNDED)) {
                    $this->orderStateMachine->transition($order, OrderStatus::REFUNDED, 'admin', $adminUserId, $reason);
                }

                $this->postRefundLedgerEntries($order, $refund);
                $this->notifications->notifyOrderEvent($order, 'refund_completed');
            }

            return $refund;
        });
    }

    private function postPaymentLedgerEntries(Order $order, PaymentIntent $intent): void
    {
        $c = strtoupper($order->currency);
        $this->ledgerService->postTransaction(
            referenceType: 'order_payment',
            referenceId: $order->id,
            idempotencyKey: "ledger:payment:{$intent->id}",
            currency: $c,
            description: "Verified customer payment for order {$order->order_number}",
            lines: [
                ['account_code' => "1000_GATEWAY_RECEIVABLE_{$c}", 'direction' => 'debit', 'amount_minor' => $order->total_minor],
                ['account_code' => "4000_TOPUP_REVENUE_{$c}", 'direction' => 'credit', 'amount_minor' => $order->total_minor],
            ],
            metadata: ['order_number' => $order->order_number, 'provider' => $intent->provider],
        );
    }

    private function postRefundLedgerEntries(Order $order, Refund $refund): void
    {
        $c = strtoupper($order->currency);
        $this->ledgerService->postTransaction(
            referenceType: 'refund',
            referenceId: $refund->id,
            idempotencyKey: "ledger:refund:{$refund->id}",
            currency: $c,
            description: "Customer refund for order {$order->order_number}",
            lines: [
                ['account_code' => "4100_REFUNDS_CONTRA_{$c}", 'direction' => 'debit', 'amount_minor' => $refund->amount_minor],
                ['account_code' => "1000_GATEWAY_RECEIVABLE_{$c}", 'direction' => 'credit', 'amount_minor' => $refund->amount_minor],
            ],
            metadata: ['order_number' => $order->order_number],
        );
    }

    private function flagPaymentMismatch(Order $order, PaymentIntent $intent, array $parsed, array $verification): void
    {
        PaymentEvent::query()->create([
            'payment_intent_id' => $intent->id,
            'event_type' => 'verification_mismatch',
            'external_event_id' => $parsed['external_event_id'],
            'from_status' => $intent->status->value,
            'to_status' => PaymentStatus::VERIFYING->value,
            'signature_valid' => true,
            'payload_redacted' => [
                'expected_amount' => $intent->amount_minor,
                'reported_amount' => $parsed['amount_minor'],
                'verified_amount' => $verification['amount_minor'],
                'expected_currency' => $intent->currency,
                'reported_currency' => $parsed['currency'],
            ],
            'created_at' => now(),
        ]);

        if ($order->status->canTransitionTo(OrderStatus::NEEDS_REVIEW)) {
            $this->orderStateMachine->transition($order, OrderStatus::NEEDS_REVIEW, 'webhook', null, 'Payment amount/currency verification mismatch.');
        }

        ReconciliationDiscrepancy::query()->create([
            'order_id' => $order->id,
            'classification' => 'fulfilled_payment_mismatch',
            'severity' => 'critical',
            'resolution_status' => 'open',
            'summary' => "Payment mismatch on order {$order->order_number}: expected {$intent->amount_minor} {$intent->currency}, received {$parsed['amount_minor']} {$parsed['currency']}.",
            'detected_at' => now(),
            'audit_history' => [['event' => 'payment_mismatch', 'at' => now()->toIso8601String()]],
        ]);
    }
}
