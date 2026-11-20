<?php

namespace App\Services\Payment\Adapters;

use App\Models\Order;
use App\Models\PaymentIntent;
use App\Services\Payment\PaymentGatewayInterface;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * ABA PayWay Merchant Adapter (Cambodia KHQR & PayWay Checkout).
 *
 * EXTERNAL PREREQUISITE:
 * Requires verified ABA PayWay Merchant ID, API Key, and RSA/Webhook configuration
 * approved by ABA Bank Cambodia. Must be validated against ABA PayWay Sandbox
 * before live commercial activation.
 */
class AbaPayWayAdapter implements PaymentGatewayInterface
{
    public function providerCode(): string
    {
        return 'aba_payway';
    }

    public function providerMode(): string
    {
        return (string) config('topup.payment.aba_payway.environment', 'sandbox');
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
            'is_mock' => false,
            'externally_verified' => false,
        ];
    }

    public function createSession(Order $order, string $idempotencyKey): array
    {
        $merchantId = (string) config('topup.payment.aba_payway.merchant_id');
        $apiKey = (string) config('topup.payment.aba_payway.api_key');
        $baseUrl = rtrim((string) config('topup.payment.aba_payway.base_url'), '/');

        if ($merchantId === '' || $apiKey === '') {
            throw new RuntimeException(
                'ABA PayWay credentials (ABA_PAYWAY_MERCHANT_ID, ABA_PAYWAY_API_KEY) are not configured.'
            );
        }

        $reqTime = now('UTC')->format('YmdHis');
        $tranId = substr(preg_replace('/[^A-Za-z0-9]/', '', $order->order_number) ?? '', 0, 20);
        $amount = $order->currency === 'KHR'
            ? (string) $order->total_minor
            : number_format($order->total_minor / 100, 2, '.', '');

        $hashInput = $reqTime.$merchantId.$tranId.$amount.$order->currency;
        $hash = base64_encode(hash_hmac('sha512', $hashInput, $apiKey, true));

        $response = Http::timeout(15)
            ->acceptJson()
            ->post("{$baseUrl}/api/payment-gateway/v1/payments/generate-qr", [
                'req_time' => $reqTime,
                'merchant_id' => $merchantId,
                'tran_id' => $tranId,
                'amount' => $amount,
                'currency' => $order->currency,
                'payment_option' => 'abapay_khqr',
                'hash' => $hash,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('ABA PayWay session creation failed with HTTP '.$response->status());
        }

        $data = $response->json();
        if (! is_array($data) || empty($data['qrString'])) {
            throw new RuntimeException('ABA PayWay returned an invalid QR response payload.');
        }

        return [
            'provider_reference' => $tranId,
            'merchant_reference' => $order->order_number,
            'qr_payload' => (string) $data['qrString'],
            'checkout_url' => $data['abapay_deeplink'] ?? null,
            'expires_at' => $order->expires_at ?? now()->addMinutes(15),
            'metadata' => [
                'merchant_id' => $merchantId,
                'req_time' => $reqTime,
            ],
        ];
    }

    public function parseAndVerifyWebhook(array $payload, array $headers, string $rawBody): array
    {
        $webhookSecret = (string) config('topup.payment.aba_payway.webhook_secret');
        $providedSig = $headers['x-payway-signature'] ?? $headers['X-PayWay-Signature'] ?? '';
        $signatureValid = $webhookSecret !== ''
            && is_string($providedSig)
            && $providedSig !== ''
            && hash_equals(
                base64_encode(hash_hmac('sha512', $rawBody, $webhookSecret, true)),
                $providedSig,
            );

        $currency = strtoupper((string) ($payload['currency'] ?? 'USD'));
        $rawAmount = (float) ($payload['amount'] ?? 0);
        $amountMinor = $currency === 'KHR' ? (int) round($rawAmount) : (int) round($rawAmount * 100);

        $statusCode = (string) ($payload['status'] ?? '');
        $normalizedStatus = $statusCode === '0' ? 'paid' : 'failed';

        return [
            'signature_valid' => $signatureValid,
            'provider_reference' => (string) ($payload['tran_id'] ?? ''),
            'external_event_id' => (string) ($payload['apv'] ?? ($payload['tran_id'] ?? '').':'.$statusCode),
            'status' => $normalizedStatus,
            'amount_minor' => $amountMinor,
            'currency' => $currency,
            'merchant_id' => (string) ($payload['merchant_id'] ?? ''),
        ];
    }

    public function verifyTransaction(PaymentIntent $intent): array
    {
        $merchantId = (string) config('topup.payment.aba_payway.merchant_id');
        $apiKey = (string) config('topup.payment.aba_payway.api_key');
        $baseUrl = rtrim((string) config('topup.payment.aba_payway.base_url'), '/');

        if ($merchantId === '' || $apiKey === '') {
            throw new RuntimeException('ABA PayWay credentials are not configured.');
        }

        $reqTime = now('UTC')->format('YmdHis');
        $hash = base64_encode(hash_hmac('sha512', $reqTime.$merchantId.$intent->provider_reference, $apiKey, true));

        $response = Http::timeout(15)
            ->acceptJson()
            ->post("{$baseUrl}/api/payment-gateway/v1/payments/check-transaction-2", [
                'req_time' => $reqTime,
                'merchant_id' => $merchantId,
                'tran_id' => $intent->provider_reference,
                'hash' => $hash,
            ]);

        if (! $response->successful()) {
            return [
                'verified' => false,
                'status' => 'verifying',
                'amount_minor' => 0,
                'currency' => $intent->currency,
                'provider_reference' => $intent->provider_reference,
                'raw_redacted' => ['http_status' => $response->status()],
            ];
        }

        $body = $response->json();
        $paid = (($body['data']['payment_status_code'] ?? null) === 0);
        $currency = strtoupper((string) ($body['data']['currency'] ?? $intent->currency));
        $rawAmount = (float) ($body['data']['payment_amount'] ?? 0);
        $amountMinor = $currency === 'KHR' ? (int) round($rawAmount) : (int) round($rawAmount * 100);

        return [
            'verified' => $paid,
            'status' => $paid ? 'paid' : 'pending',
            'amount_minor' => $amountMinor,
            'currency' => $currency,
            'provider_reference' => $intent->provider_reference,
            'raw_redacted' => ['payment_status_code' => $body['data']['payment_status_code'] ?? null],
        ];
    }

    public function requestRefund(PaymentIntent $intent, int $amountMinor, string $reason, string $idempotencyKey): array
    {
        return [
            'supported' => false,
            'status' => 'needs_review',
            'provider_refund_reference' => null,
            'message' => 'ABA PayWay automated refund requires merchant-portal/API refund capability verification.',
        ];
    }
}
