<?php

namespace App\Services\Fulfillment;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Jobs\ReconcileFulfillmentJob;
use App\Jobs\SubmitFulfillmentJob;
use App\Models\Fulfillment;
use App\Models\FulfillmentReconciliation;
use App\Models\Order;
use App\Models\OutboxEvent;
use App\Models\ReconciliationDiscrepancy;
use App\Models\SystemSetting;
use App\Services\Accounting\LedgerService;
use App\Services\Notification\NotificationService;
use App\Services\Order\OrderStateMachine;
use DomainException;
use Illuminate\Support\Facades\DB;

class FulfillmentOrchestrator
{
    public function __construct(
        private readonly SupplierRouter $router,
        private readonly SupplierAdapterFactory $adapterFactory,
        private readonly FulfillmentStateMachine $fulfillmentStateMachine,
        private readonly OrderStateMachine $orderStateMachine,
        private readonly FulfillmentAuditService $auditService,
        private readonly LedgerService $ledgerService,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * Step 1-5: Verify paid invariant, lock order, ensure no duplicate fulfillment,
     * persist fulfillment + outbox atomically, and dispatch SubmitFulfillmentJob.
     */
    public function initiateForPaidOrder(string $orderId): Fulfillment
    {
        $fulfillment = DB::transaction(function () use ($orderId): Fulfillment {
            /** @var Order $order */
            $order = Order::query()->lockForUpdate()->findOrFail($orderId);

            // HARD INVARIANT: Zero fulfillment before verified payment.
            if ($order->payment_status !== PaymentStatus::PAID) {
                throw new DomainException("Cannot initiate fulfillment for unpaid order [{$order->order_number}].");
            }

            // HARD INVARIANT: Confirm no existing fulfillment is already in progress or completed.
            $existing = Fulfillment::query()->where('order_id', $order->id)->lockForUpdate()->first();
            if ($existing) {
                return $existing;
            }

            // Stable idempotency key per logical order fulfillment (never regenerated on retry)
            $idempotencyKey = "fulfill:{$order->id}:v1";

            $fulfillment = Fulfillment::query()->create([
                'order_id' => $order->id,
                'idempotency_key' => $idempotencyKey,
                'status' => FulfillmentStatus::NOT_STARTED,
                'attempt_count' => 0,
                'submission_sent' => false,
            ]);

            if ((bool) SystemSetting::getValue('fulfillment_global_pause', false)) {
                $this->fulfillmentStateMachine->transition($fulfillment, FulfillmentStatus::QUEUED, 'system', null, 'Queued while global fulfillment pause is active.');
                $this->fulfillmentStateMachine->transition($fulfillment, FulfillmentStatus::NEEDS_REVIEW, 'system', null, 'Global fulfillment pause active; held for review.');
                $order->fulfillment_status = FulfillmentStatus::NEEDS_REVIEW;
                $order->save();
                if ($order->status->canTransitionTo(OrderStatus::NEEDS_REVIEW)) {
                    $this->orderStateMachine->transition($order, OrderStatus::NEEDS_REVIEW, 'system', null, 'Global fulfillment pause active.');
                }

                return $fulfillment;
            }

            $this->fulfillmentStateMachine->transition($fulfillment, FulfillmentStatus::QUEUED, 'system', null, 'Payment verified; fulfillment queued.');
            $order->fulfillment_status = FulfillmentStatus::QUEUED;
            $order->save();

            if ($order->status->canTransitionTo(OrderStatus::FULFILLING)) {
                $this->orderStateMachine->transition($order, OrderStatus::FULFILLING, 'system', null, 'Fulfillment queued.');
            }

            OutboxEvent::query()->firstOrCreate(
                ['idempotency_key' => "outbox:fulfillment_queued:{$fulfillment->id}"],
                [
                    'aggregate_type' => 'fulfillment',
                    'aggregate_id' => $fulfillment->id,
                    'event_type' => 'fulfillment.queued',
                    'payload' => ['fulfillment_id' => $fulfillment->id, 'order_id' => $order->id],
                    'status' => 'pending',
                    'available_at' => now(),
                ],
            );

            return $fulfillment;
        });

        if ($fulfillment->status === FulfillmentStatus::QUEUED) {
            SubmitFulfillmentJob::dispatch($fulfillment->id);
        }

        return $fulfillment;
    }

    /**
     * Step 6-13: Execute supplier submission safely with idempotency, attempt auditing,
     * and safe timeout/uncertain handling.
     */
    public function executeSubmission(string $fulfillmentId): Fulfillment
    {
        return DB::transaction(function () use ($fulfillmentId): Fulfillment {
            /** @var Fulfillment $fulfillment */
            $fulfillment = Fulfillment::query()->lockForUpdate()->findOrFail($fulfillmentId);
            /** @var Order $order */
            $order = Order::query()->with('gamePackage')->lockForUpdate()->findOrFail($fulfillment->order_id);

            // HARD INVARIANT: Never fulfill unpaid order
            if ($order->payment_status !== PaymentStatus::PAID) {
                throw new DomainException('Refusing submission for unpaid order.');
            }

            // HARD INVARIANT: Never duplicate a completed or already-submitted fulfillment
            if ($fulfillment->status === FulfillmentStatus::SUCCEEDED) {
                return $fulfillment;
            }

            if ($fulfillment->submission_sent && in_array($fulfillment->status, [FulfillmentStatus::PROCESSING, FulfillmentStatus::UNCERTAIN], true)) {
                // Already submitted to supplier -> must reconcile instead of creating a duplicate submission
                DB::afterCommit(fn () => ReconcileFulfillmentJob::dispatch($fulfillment->id));

                return $fulfillment;
            }

            if ($fulfillment->status !== FulfillmentStatus::QUEUED) {
                return $fulfillment;
            }

            $candidate = $this->router->selectCandidate($order->gamePackage, $order->region_code);
            if (! $candidate) {
                $this->fulfillmentStateMachine->transition($fulfillment, FulfillmentStatus::NEEDS_REVIEW, 'system', null, 'No eligible healthy supplier available.');
                $order->fulfillment_status = FulfillmentStatus::NEEDS_REVIEW;
                $order->save();
                if ($order->status->canTransitionTo(OrderStatus::NEEDS_REVIEW)) {
                    $this->orderStateMachine->transition($order, OrderStatus::NEEDS_REVIEW, 'system', null, 'No eligible healthy supplier available.');
                }
                $this->notifications->notifyOrderEvent($order, 'order_requires_support');

                return $fulfillment;
            }

            $supplier = $candidate->supplier;
            $adapter = $this->adapterFactory->forSupplier($supplier);

            $fulfillment->supplier_id = $supplier->id;
            $fulfillment->supplier_product_id = $candidate->id;
            $fulfillment->submission_sent = true;
            $fulfillment->submitted_at = now();
            $fulfillment->save();

            $this->fulfillmentStateMachine->transition($fulfillment, FulfillmentStatus::PROCESSING, 'system', null, "Submitting to supplier [{$supplier->code}].");
            $order->fulfillment_status = FulfillmentStatus::PROCESSING;
            $order->save();
            $this->notifications->notifyOrderEvent($order, 'topup_processing');

            $playerFields = $order->player_fields_encrypted ?? [];
            $startedAt = microtime(true);
            $result = $adapter->submitOrder($fulfillment, $candidate, $playerFields);
            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

            $this->auditService->recordAttempt(
                fulfillment: $fulfillment,
                supplierId: $supplier->id,
                action: 'submit',
                outcome: $result['outcome'],
                externalReference: $result['external_reference'],
                httpStatus: $result['http_status'],
                durationMs: $durationMs,
                errorCode: $result['error_code'],
                errorMessage: $result['error_message'],
                requestPayload: [
                    'external_sku' => $candidate->external_sku,
                    'region_code' => $order->region_code,
                    'idempotency_key' => $fulfillment->idempotency_key,
                ],
                responsePayload: $result['raw_redacted'],
            );

            $fulfillment->external_reference = $result['external_reference'];
            $fulfillment->supplier_transaction_id = $result['supplier_transaction_id'];
            $fulfillment->supplier_response_redacted = $result['raw_redacted'];

            if ($result['outcome'] === 'succeeded') {
                $this->markFulfillmentSucceeded($order, $fulfillment, $result['actual_cost_minor'] ?? $candidate->cost_minor, $result['currency']);
            } elseif ($result['outcome'] === 'failed_definitive') {
                // Definitive pre-execution failure: safe to mark failed (submission did not execute)
                $fulfillment->submission_sent = false;
                $fulfillment->failure_code = $result['error_code'];
                $fulfillment->failure_reason = $result['error_message'];
                $fulfillment->save();

                $this->fulfillmentStateMachine->transition($fulfillment, FulfillmentStatus::FAILED, 'system', null, $result['error_message']);
                $order->fulfillment_status = FulfillmentStatus::FAILED;
                $order->save();

                if ($order->status->canTransitionTo(OrderStatus::FAILED)) {
                    $this->orderStateMachine->transition($order, OrderStatus::FAILED, 'system', null, $result['error_message']);
                }
                $this->notifications->notifyOrderEvent($order, 'order_requires_support');
            } else {
                // 'timeout_uncertain' or 'pending': NEVER assume failure; enter UNCERTAIN and reconcile!
                $fulfillment->failure_code = $result['error_code'];
                $fulfillment->failure_reason = $result['error_message'];
                $fulfillment->save();

                $this->fulfillmentStateMachine->transition($fulfillment, FulfillmentStatus::UNCERTAIN, 'system', null, 'Supplier returned timeout/pending; reconciling via status lookup.');
                $order->fulfillment_status = FulfillmentStatus::UNCERTAIN;
                $order->save();

                DB::afterCommit(fn () => ReconcileFulfillmentJob::dispatch($fulfillment->id));
            }

            // Mark outbox dispatched
            OutboxEvent::query()
                ->where('aggregate_id', $fulfillment->id)
                ->where('status', 'pending')
                ->update(['status' => 'dispatched', 'dispatched_at' => now()]);

            return $fulfillment;
        });
    }

    /**
     * Reconcile an uncertain/pending fulfillment using supplier status lookup.
     * If status lookup is unavailable or returns unknown, escalate to NEEDS_REVIEW (never blind retry!).
     */
    public function reconcileUncertain(string $fulfillmentId): Fulfillment
    {
        return DB::transaction(function () use ($fulfillmentId): Fulfillment {
            /** @var Fulfillment $fulfillment */
            $fulfillment = Fulfillment::query()->with(['order', 'supplier', 'supplierProduct'])->lockForUpdate()->findOrFail($fulfillmentId);
            if (! in_array($fulfillment->status, [FulfillmentStatus::UNCERTAIN, FulfillmentStatus::PROCESSING], true)) {
                return $fulfillment;
            }

            $order = $fulfillment->order;
            $supplier = $fulfillment->supplier;
            if (! $supplier) {
                $this->escalateToNeedsReview($order, $fulfillment, 'Missing supplier context during reconciliation.');

                return $fulfillment;
            }

            $adapter = $this->adapterFactory->forSupplier($supplier);
            $startedAt = microtime(true);
            $lookup = $adapter->lookupStatus($fulfillment);
            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

            $this->auditService->recordAttempt(
                fulfillment: $fulfillment,
                supplierId: $supplier->id,
                action: 'status_lookup',
                outcome: $lookup['outcome'],
                externalReference: $fulfillment->external_reference,
                httpStatus: 200,
                durationMs: $durationMs,
                responsePayload: $lookup['raw_redacted'],
            );

            if ($lookup['supported'] && $lookup['outcome'] === 'succeeded') {
                $fulfillment->supplier_transaction_id = $lookup['supplier_transaction_id'] ?? $fulfillment->supplier_transaction_id;
                $this->markFulfillmentSucceeded(
                    $order,
                    $fulfillment,
                    $lookup['actual_cost_minor'] ?? ($fulfillment->supplierProduct?->cost_minor ?? $order->supplier_cost_minor),
                    $fulfillment->supplierProduct?->currency ?? $order->supplier_cost_currency,
                );

                FulfillmentReconciliation::query()->create([
                    'fulfillment_id' => $fulfillment->id,
                    'status' => 'matched',
                    'supplier_reported_status' => 'succeeded',
                    'expected_cost_minor' => $order->supplier_cost_minor,
                    'reported_cost_minor' => $fulfillment->actual_supplier_cost_minor,
                    'notes' => 'Uncertain fulfillment resolved as succeeded via supplier status lookup.',
                    'reconciled_at' => now(),
                ]);

                return $fulfillment;
            }

            if ($lookup['supported'] && $lookup['outcome'] === 'failed_definitive') {
                $fulfillment->submission_sent = false;
                $fulfillment->save();
                $this->fulfillmentStateMachine->transition($fulfillment, FulfillmentStatus::FAILED, 'system', null, 'Supplier status lookup confirmed failure.');
                $order->fulfillment_status = FulfillmentStatus::FAILED;
                $order->save();
                if ($order->status->canTransitionTo(OrderStatus::FAILED)) {
                    $this->orderStateMachine->transition($order, OrderStatus::FAILED, 'system', null, 'Supplier status lookup confirmed failure.');
                }

                return $fulfillment;
            }

            // Cannot rule out duplicate execution -> MUST enter needs_review!
            $this->escalateToNeedsReview($order, $fulfillment, 'Supplier status could not be confirmed; placed in manual review to prevent duplicate top-up.');

            return $fulfillment;
        });
    }

    /**
     * Safe admin retry: ONLY permitted when fulfillment has definitively FAILED and was not executed.
     */
    public function retrySafeEligible(Fulfillment $fulfillment, string $adminUserId): Fulfillment
    {
        if ($fulfillment->status !== FulfillmentStatus::FAILED || $fulfillment->submission_sent) {
            throw new DomainException(
                'Automatic retry is only allowed for definitively failed fulfillments where execution did not occur.'
            );
        }

        $this->fulfillmentStateMachine->transition($fulfillment, FulfillmentStatus::QUEUED, 'admin', $adminUserId, 'Operator approved safe retry.');
        $order = $fulfillment->order;
        $order->fulfillment_status = FulfillmentStatus::QUEUED;
        $order->save();
        if ($order->status->canTransitionTo(OrderStatus::FULFILLING)) {
            $this->orderStateMachine->transition($order, OrderStatus::FULFILLING, 'admin', $adminUserId, 'Operator approved safe retry.');
        }

        SubmitFulfillmentJob::dispatch($fulfillment->id);

        return $fulfillment->fresh();
    }

    private function markFulfillmentSucceeded(Order $order, Fulfillment $fulfillment, int $actualCostMinor, string $costCurrency): void
    {
        $fulfillment->actual_supplier_cost_minor = $actualCostMinor;
        $fulfillment->actual_supplier_currency = $costCurrency;
        $fulfillment->save();

        // Check for supplier cost mismatch discrepancy
        if ($actualCostMinor !== $order->supplier_cost_minor && $costCurrency === $order->supplier_cost_currency) {
            ReconciliationDiscrepancy::query()->create([
                'order_id' => $order->id,
                'classification' => 'supplier_cost_mismatch',
                'severity' => 'medium',
                'resolution_status' => 'open',
                'summary' => "Supplier cost mismatch on {$order->order_number}: expected {$order->supplier_cost_minor}, actual {$actualCostMinor} {$costCurrency}.",
                'detected_at' => now(),
                'audit_history' => [['event' => 'cost_mismatch_detected', 'at' => now()->toIso8601String()]],
            ]);
        }

        $this->fulfillmentStateMachine->transition($fulfillment, FulfillmentStatus::SUCCEEDED, 'system', null, 'Supplier confirmed delivery.');

        $order->fulfillment_status = FulfillmentStatus::SUCCEEDED;
        $order->save();

        if ($order->status->canTransitionTo(OrderStatus::COMPLETED)) {
            $this->orderStateMachine->transition($order, OrderStatus::COMPLETED, 'system', null, 'Fulfillment confirmed by supplier.');
        }

        // Post COGS balanced double-entry journal in supplier cost currency
        $c = strtoupper($costCurrency);
        $this->ledgerService->postTransaction(
            referenceType: 'fulfillment_cost',
            referenceId: $fulfillment->id,
            idempotencyKey: "ledger:cogs:{$fulfillment->id}",
            currency: $c,
            description: "Supplier COGS for order {$order->order_number}",
            lines: [
                ['account_code' => "5000_SUPPLIER_COGS_{$c}", 'direction' => 'debit', 'amount_minor' => $actualCostMinor],
                ['account_code' => "1100_SUPPLIER_WALLET_{$c}", 'direction' => 'credit', 'amount_minor' => $actualCostMinor],
            ],
            metadata: ['order_number' => $order->order_number, 'supplier_id' => $fulfillment->supplier_id],
        );

        $this->notifications->notifyOrderEvent($order, 'topup_completed');
    }

    private function escalateToNeedsReview(Order $order, Fulfillment $fulfillment, string $reason): void
    {
        $this->fulfillmentStateMachine->transition($fulfillment, FulfillmentStatus::NEEDS_REVIEW, 'system', null, $reason);
        $order->fulfillment_status = FulfillmentStatus::NEEDS_REVIEW;
        $order->save();

        if ($order->status->canTransitionTo(OrderStatus::NEEDS_REVIEW)) {
            $this->orderStateMachine->transition($order, OrderStatus::NEEDS_REVIEW, 'system', null, $reason);
        }

        FulfillmentReconciliation::query()->create([
            'fulfillment_id' => $fulfillment->id,
            'status' => 'unresolved',
            'supplier_reported_status' => 'unknown',
            'notes' => $reason,
            'reconciled_at' => now(),
        ]);

        ReconciliationDiscrepancy::query()->create([
            'order_id' => $order->id,
            'classification' => 'paid_not_fulfilled',
            'severity' => 'high',
            'resolution_status' => 'open',
            'summary' => $reason,
            'detected_at' => now(),
            'audit_history' => [['event' => 'escalated_needs_review', 'at' => now()->toIso8601String()]],
        ]);

        $this->notifications->notifyOrderEvent($order, 'order_requires_support');
    }
}
