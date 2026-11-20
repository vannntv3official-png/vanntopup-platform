<?php

namespace App\Services\Payment;

use App\Models\Order;
use App\Models\PaymentIntent;

interface PaymentGatewayInterface
{
    public function providerCode(): string;

    public function providerMode(): string;

    /**
     * @return array<string, mixed>
     */
    public function capabilities(): array;

    /**
     * @return array{
     *   provider_reference: string,
     *   merchant_reference: string,
     *   qr_payload: ?string,
     *   checkout_url: ?string,
     *   expires_at: \Carbon\CarbonInterface,
     *   metadata: array<string, mixed>
     * }
     */
    public function createSession(Order $order, string $idempotencyKey): array;

    /**
     * Verify webhook signature and normalize webhook payload.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     * @return array{
     *   signature_valid: bool,
     *   provider_reference: string,
     *   external_event_id: string,
     *   status: string,
     *   amount_minor: int,
     *   currency: string,
     *   merchant_id: string
     * }
     */
    public function parseAndVerifyWebhook(array $payload, array $headers, string $rawBody): array;

    /**
     * Server-to-server transaction verification.
     *
     * @return array{
     *   verified: bool,
     *   status: string,
     *   amount_minor: int,
     *   currency: string,
     *   provider_reference: string,
     *   raw_redacted: array<string, mixed>
     * }
     */
    public function verifyTransaction(PaymentIntent $intent): array;

    /**
     * @return array{
     *   supported: bool,
     *   status: string,
     *   provider_refund_reference: ?string,
     *   message: string
     * }
     */
    public function requestRefund(PaymentIntent $intent, int $amountMinor, string $reason, string $idempotencyKey): array;
}
