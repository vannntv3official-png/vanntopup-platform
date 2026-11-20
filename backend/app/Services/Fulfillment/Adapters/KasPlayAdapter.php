<?php

namespace App\Services\Fulfillment\Adapters;

use App\Models\Fulfillment;
use App\Models\Game;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Services\Fulfillment\TopUpSupplierInterface;
use RuntimeException;

/**
 * KAS Play Authorized Reseller Adapter.
 *
 * EXTERNAL PREREQUISITE:
 * Requires verified KAS Play reseller agreement, API key, secret, and IP whitelist.
 * Implementation is isolated behind TopUpSupplierInterface and remains unverified
 * externally until sandbox/live reseller credentials are provisioned.
 */
class KasPlayAdapter implements TopUpSupplierInterface
{
    public function supplierCode(): string
    {
        return 'kas_play';
    }

    public function validatePlayer(Game $game, string $regionCode, array $playerFields): array
    {
        $this->assertConfigured();

        return [
            'valid' => false,
            'supported' => false,
            'player_name' => null,
            'message' => 'KAS Play live player validation requires sandbox verification.',
        ];
    }

    public function submitOrder(Fulfillment $fulfillment, SupplierProduct $supplierProduct, array $playerFields): array
    {
        $this->assertConfigured();

        return [
            'outcome' => 'timeout_uncertain',
            'external_reference' => null,
            'supplier_transaction_id' => null,
            'actual_cost_minor' => null,
            'currency' => $supplierProduct->currency,
            'http_status' => null,
            'error_code' => 'UNVERIFIED_EXTERNAL_PROVIDER',
            'error_message' => 'KAS Play API endpoint requires verified reseller credentials and sandbox certification.',
            'raw_redacted' => ['provider' => 'kas_play'],
        ];
    }

    public function lookupStatus(Fulfillment $fulfillment): array
    {
        return [
            'supported' => false,
            'outcome' => 'unknown',
            'supplier_transaction_id' => null,
            'actual_cost_minor' => null,
            'raw_redacted' => ['provider' => 'kas_play'],
        ];
    }

    public function checkBalance(Supplier $supplier): array
    {
        return ['supported' => false, 'currency' => 'USD', 'available_balance_minor' => 0];
    }

    public function healthCheck(Supplier $supplier): array
    {
        $configured = ! empty(config('topup.suppliers.kas_play.api_key'));

        return [
            'healthy' => false,
            'latency_ms' => 0,
            'error_message' => $configured ? 'Pending sandbox connectivity verification' : 'Credentials not configured',
        ];
    }

    private function assertConfigured(): void
    {
        if (empty(config('topup.suppliers.kas_play.base_url')) || empty(config('topup.suppliers.kas_play.api_key'))) {
            throw new RuntimeException('KAS Play reseller credentials are not configured.');
        }
    }
}
