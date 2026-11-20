<?php

namespace App\Services\Fulfillment;

use App\Models\Fulfillment;
use App\Models\Game;
use App\Models\Supplier;
use App\Models\SupplierProduct;

interface TopUpSupplierInterface
{
    public function supplierCode(): string;

    /**
     * @param  array<string, string>  $playerFields
     * @return array{valid: bool, supported: bool, player_name: ?string, message: string}
     */
    public function validatePlayer(Game $game, string $regionCode, array $playerFields): array;

    /**
     * Submit top-up order with a stable idempotency key.
     *
     * @param  array<string, string>  $playerFields
     * @return array{
     *   outcome: 'succeeded'|'pending'|'failed_definitive'|'timeout_uncertain',
     *   external_reference: ?string,
     *   supplier_transaction_id: ?string,
     *   actual_cost_minor: ?int,
     *   currency: string,
     *   http_status: ?int,
     *   error_code: ?string,
     *   error_message: ?string,
     *   raw_redacted: array<string, mixed>
     * }
     */
    public function submitOrder(
        Fulfillment $fulfillment,
        SupplierProduct $supplierProduct,
        array $playerFields,
    ): array;

    /**
     * Lookup status of a previously submitted fulfillment by its stable idempotency key / external reference.
     *
     * @return array{
     *   supported: bool,
     *   outcome: 'succeeded'|'pending'|'failed_definitive'|'unknown',
     *   supplier_transaction_id: ?string,
     *   actual_cost_minor: ?int,
     *   raw_redacted: array<string, mixed>
     * }
     */
    public function lookupStatus(Fulfillment $fulfillment): array;

    /**
     * @return array{supported: bool, currency: string, available_balance_minor: int}
     */
    public function checkBalance(Supplier $supplier): array;

    /**
     * @return array{healthy: bool, latency_ms: int, error_message: ?string}
     */
    public function healthCheck(Supplier $supplier): array;
}
