<?php

namespace App\Services\Payment\Adapters;

use App\Models\Order;
use App\Models\PaymentIntent;
use App\Services\Payment\PaymentGatewayInterface;
use Illuminate\Support\Str;

/**
 * DEVELOPMENT & TESTING ONLY — Deterministic local KHQR / PayWay simulator.
 * Blocked automatically in production by ProductionSafetyGuard.
 */
class MockPayWayAdapter implements PaymentGatewayInterface
{
    public function providerCode(): string
    {
        return 'mock_payway';
    }

    public function providerMode(): string
    {
        return 'mock';
    }

    public function capabilities(): array
    {
        return [
            'khqr' => true,
            'hosted_checkout' => true,
            'webhooks' => true,
            'server_verify' => true,
            'refunds' => true,
            'partial_refunds' => false,
            'currencies' => ['USD', 'KHR'],
            'is_mock' => true,
        ];
    }

    public function createSession(Order $order, string $idempotencyKey): array
    {
        $providerRef = 'MOCK-PW-'.strtoupper(Str::random(12));
        $merchantRef = $order->order_number;
        $expiresAt = $order->expires_at ?? now()->addMinutes(15);

        $qrPayload = sprintf(
            '00020101021229400016KH.COM.MOCKPAYWAY0116%s520458165303%s5406%s5802KH5909VannTopUp6010Phnom Penh6304MOCK',
            $providerRef,
            $order->currency === 'KHR' ? '116' : '840',
            $order->currency === 'KHR' ? (string) $order->total_minor : number_format($order->total_minor / 100, 2, '.', ''),
        );

        return [
            'provider_reference' => $providerRef,
            'merchant_reference' => $merchantRef,
            'qr_payload' => $qrPayload,
            'checkout_url' => null,
            'expires_at' => $expiresAt,
            'metadata' => [
                'mock_notice' => 'DEVELOPMENT SIMULATOR ONLY — NO REAL MONEY',
                'merchant_id' => 'MOCK_MERCHANT_KH',
            ],
        ];
    }

    public function parseAndVerifyWebhook(array $payload, array $headers, string $rawBody): array
    {
        $secret = (string) config('topup.payment.mock_webhook_secret');
        $providedSig = $headers['x-mock-signature'] ?? $headers['X-Mock-Signature'] ?? ($payload['signature'] ?? '');
        $expectedSig = $this->computeSignature(
            (string) ($payload['provider_reference'] ?? ''),
            (string) ($payload['status'] ?? ''),
            (int) ($payload['amount_minor'] ?? 0),
            (string) ($payload['currency'] ?? ''),
            $secret,
        );

        return [
            'signature_valid' => is_string($providedSig) && $providedSig !== '' && hash_equals($expectedSig, $providedSig),
            'provider_reference' => (string) ($payload['provider_reference'] ?? ''),
            'external_event_id' => (string) ($payload['event_id'] ?? Str::uuid()),
            'status' => strtolower((string) ($payload['status'] ?? 'failed')),
            'amount_minor' => (int) ($payload['amount_minor'] ?? 0),
            'currency' => strtoupper((string) ($payload['currency'] ?? '')),
            'merchant_id' => (string) ($payload['merchant_id'] ?? 'MOCK_MERCHANT_KH'),
        ];
    }

    public function verifyTransaction(PaymentIntent $intent): array
    {
        $simulatedStatus = $intent->provider_metadata['simulated_status'] ?? $intent->status->value;

        return [
            'verified' => $simulatedStatus === 'paid',
            'status' => $simulatedStatus,
            'amount_minor' => (int) ($intent->provider_metadata['simulated_amount_minor'] ?? $intent->amount_minor),
            'currency' => (string) ($intent->provider_metadata['simulated_currency'] ?? $intent->currency),
            'provider_reference' => $intent->provider_reference,
            'raw_redacted' => [
                'provider' => 'mock_payway',
                'simulated_status' => $simulatedStatus,
                'checked_at' => now()->toIso8601String(),
            ],
        ];
    }

    public function requestRefund(PaymentIntent $intent, int $amountMinor, string $reason, string $idempotencyKey): array
    {
        return [
            'supported' => true,
            'status' => 'succeeded',
            'provider_refund_reference' => 'MOCK-RF-'.strtoupper(Str::random(10)),
            'message' => 'Simulated mock refund succeeded.',
        ];
    }

    public function computeSignature(string $providerReference, string $status, int $amountMinor, string $currency, string $secret): string
    {
        return hash_hmac('sha256', "{$providerReference}|{$status}|{$amountMinor}|{$currency}", $secret);
    }
}
