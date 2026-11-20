<?php

namespace App\Services\Fulfillment\Adapters;

use App\Models\Fulfillment;
use App\Models\Game;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Services\Fulfillment\TopUpSupplierInterface;
use Illuminate\Support\Str;

/**
 * DEVELOPMENT & TESTING ONLY — Deterministic mock game top-up supplier.
 *
 * Deterministic test triggers (pass in player_id / user_id field):
 * - "999999999" or contains "FORCE_FAIL"    -> definitive supplier rejection (safe for fallback/refund)
 * - "888888888" or contains "FORCE_TIMEOUT" -> network timeout (enters uncertain -> reconciliation)
 * - "777777777" or contains "FORCE_PENDING" -> pending on submit, succeeded on status lookup
 * - "666666666" or contains "FORCE_UNRESOLVED" -> timeout on submit AND unknown on lookup -> needs_review
 * - Any other valid player ID               -> immediate deterministic success
 */
class MockSupplierAdapter implements TopUpSupplierInterface
{
    public function supplierCode(): string
    {
        return 'mock_supplier';
    }

    public function validatePlayer(Game $game, string $regionCode, array $playerFields): array
    {
        $primaryId = (string) (reset($playerFields) ?: '');
        if ($primaryId === '' || str_contains($primaryId, 'INVALID')) {
            return [
                'valid' => false,
                'supported' => true,
                'player_name' => null,
                'message' => 'Player account not found in mock registry.',
            ];
        }

        $suffix = substr($primaryId, -4);

        return [
            'valid' => true,
            'supported' => true,
            'player_name' => "KH_Player_{$suffix}",
            'message' => 'Mock player account verified (development mode).',
        ];
    }

    public function submitOrder(
        Fulfillment $fulfillment,
        SupplierProduct $supplierProduct,
        array $playerFields,
    ): array {
        $primaryId = strtoupper((string) (reset($playerFields) ?: ''));

        if ($primaryId === '999999999' || str_contains($primaryId, 'FORCE_FAIL')) {
            return [
                'outcome' => 'failed_definitive',
                'external_reference' => null,
                'supplier_transaction_id' => null,
                'actual_cost_minor' => null,
                'currency' => $supplierProduct->currency,
                'http_status' => 422,
                'error_code' => 'PLAYER_NOT_ELIGIBLE',
                'error_message' => 'Mock supplier definitively rejected top-up before execution.',
                'raw_redacted' => ['mock' => true, 'trigger' => 'FORCE_FAIL'],
            ];
        }

        if ($primaryId === '888888888' || str_contains($primaryId, 'FORCE_TIMEOUT') || $primaryId === '666666666' || str_contains($primaryId, 'FORCE_UNRESOLVED')) {
            return [
                'outcome' => 'timeout_uncertain',
                'external_reference' => 'MOCK-EXT-'.substr(hash('sha256', $fulfillment->idempotency_key), 0, 12),
                'supplier_transaction_id' => null,
                'actual_cost_minor' => null,
                'currency' => $supplierProduct->currency,
                'http_status' => 504,
                'error_code' => 'UPSTREAM_TIMEOUT',
                'error_message' => 'Simulated supplier gateway timeout; execution state uncertain.',
                'raw_redacted' => ['mock' => true, 'trigger' => $primaryId],
            ];
        }

        if ($primaryId === '777777777' || str_contains($primaryId, 'FORCE_PENDING')) {
            return [
                'outcome' => 'pending',
                'external_reference' => 'MOCK-EXT-'.substr(hash('sha256', $fulfillment->idempotency_key), 0, 12),
                'supplier_transaction_id' => 'MOCK-TX-'.strtoupper(Str::random(10)),
                'actual_cost_minor' => $supplierProduct->cost_minor,
                'currency' => $supplierProduct->currency,
                'http_status' => 202,
                'error_code' => null,
                'error_message' => null,
                'raw_redacted' => ['mock' => true, 'status' => 'pending'],
            ];
        }

        return [
            'outcome' => 'succeeded',
            'external_reference' => 'MOCK-EXT-'.substr(hash('sha256', $fulfillment->idempotency_key), 0, 12),
            'supplier_transaction_id' => 'MOCK-TX-'.strtoupper(Str::random(10)),
            'actual_cost_minor' => $supplierProduct->cost_minor,
            'currency' => $supplierProduct->currency,
            'http_status' => 200,
            'error_code' => null,
            'error_message' => null,
            'raw_redacted' => ['mock' => true, 'status' => 'delivered'],
        ];
    }

    public function lookupStatus(Fulfillment $fulfillment): array
    {
        $playerFields = $fulfillment->order?->player_fields_encrypted ?? [];
        $primaryId = strtoupper((string) (reset($playerFields) ?: ''));

        if ($primaryId === '666666666' || str_contains($primaryId, 'FORCE_UNRESOLVED')) {
            return [
                'supported' => true,
                'outcome' => 'unknown',
                'supplier_transaction_id' => null,
                'actual_cost_minor' => null,
                'raw_redacted' => ['mock' => true, 'status' => 'unknown'],
            ];
        }

        return [
            'supported' => true,
            'outcome' => 'succeeded',
            'supplier_transaction_id' => $fulfillment->supplier_transaction_id ?: 'MOCK-TX-RECON-'.strtoupper(Str::random(8)),
            'actual_cost_minor' => $fulfillment->supplierProduct?->cost_minor,
            'raw_redacted' => ['mock' => true, 'reconciled' => true, 'status' => 'delivered'],
        ];
    }

    public function checkBalance(Supplier $supplier): array
    {
        return [
            'supported' => true,
            'currency' => 'USD',
            'available_balance_minor' => 500_000, // $5,000.00 mock reseller credit
        ];
    }

    public function healthCheck(Supplier $supplier): array
    {
        return [
            'healthy' => true,
            'latency_ms' => 18,
            'error_message' => null,
        ];
    }
}
