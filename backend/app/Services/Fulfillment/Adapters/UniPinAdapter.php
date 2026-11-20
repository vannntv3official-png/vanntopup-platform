<?php

namespace App\Services\Fulfillment\Adapters;

use App\Models\Fulfillment;
use App\Models\Game;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Services\Fulfillment\TopUpSupplierInterface;
use RuntimeException;

/**
 * UniPin Partner API Adapter.
 *
 * EXTERNAL PREREQUISITE:
 * Requires approved UniPin partner account, Partner ID, Secret Key, and sandbox certification.
 */
class UniPinAdapter implements TopUpSupplierInterface
{
    public function supplierCode(): string
    {
        return 'unipin';
    }

    public function validatePlayer(Game $game, string $regionCode, array $playerFields): array
    {
        $this->assertConfigured();

        return [
            'valid' => false,
            'supported' => false,
            'player_name' => null,
            'message' => 'UniPin live player validation requires partner sandbox verification.',
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
            'error_message' => 'UniPin API endpoint requires verified partner credentials and sandbox certification.',
            'raw_redacted' => ['provider' => 'unipin'],
        ];
    }

    public function lookupStatus(Fulfillment $fulfillment): array
    {
        return [
            'supported' => false,
            'outcome' => 'unknown',
            'supplier_transaction_id' => null,
            'actual_cost_minor' => null,
            'raw_redacted' => ['provider' => 'unipin'],
        ];
    }

    public function checkBalance(Supplier $supplier): array
    {
        return ['supported' => false, 'currency' => 'USD', 'available_balance_minor' => 0];
    }

    public function healthCheck(Supplier $supplier): array
    {
        $configured = ! empty(config('topup.suppliers.unipin.partner_id'));

        return [
            'healthy' => false,
            'latency_ms' => 0,
            'error_message' => $configured ? 'Pending sandbox connectivity verification' : 'Credentials not configured',
        ];
    }

    private function assertConfigured(): void
    {
        if (empty(config('topup.suppliers.unipin.base_url')) || empty(config('topup.suppliers.unipin.partner_id'))) {
            throw new RuntimeException('UniPin partner credentials are not configured.');
        }
    }
}
