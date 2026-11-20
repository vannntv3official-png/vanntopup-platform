<?php

namespace App\Services\Fulfillment;

use App\Models\GamePackage;
use App\Models\SupplierProduct;
use App\Models\SystemSetting;

class SupplierRouter
{
    /**
     * Select the best eligible, authorized, healthy supplier product for a game package & region.
     * Excludes paused/maintenance suppliers and any excluded supplier IDs (for safe pre-submission fallback).
     *
     * @param  list<string>  $excludedSupplierIds
     */
    public function selectCandidate(
        GamePackage $package,
        string $regionCode,
        array $excludedSupplierIds = [],
    ): ?SupplierProduct {
        if ((bool) SystemSetting::getValue('fulfillment_global_pause', false)) {
            return null;
        }

        $candidates = SupplierProduct::query()
            ->with(['supplier.capabilities', 'supplier.balanceSnapshots' => fn ($q) => $q->latest('captured_at')->limit(1)])
            ->where('game_package_id', $package->id)
            ->whereIn('region_code', [$regionCode, 'GLOBAL'])
            ->where('is_authorized', true)
            ->where('is_available', true)
            ->whereHas('supplier', function ($query) use ($excludedSupplierIds): void {
                $query->where('is_active', true)
                    ->where('is_paused', false)
                    ->where('is_maintenance', false)
                    ->where('health_status', '!=', 'down');

                if ($excludedSupplierIds !== []) {
                    $query->whereNotIn('id', $excludedSupplierIds);
                }
            })
            ->get();

        if ($candidates->isEmpty()) {
            return null;
        }

        // Filter by known balance if balance snapshot exists and currency matches
        $eligible = $candidates->filter(function (SupplierProduct $product): bool {
            $snapshot = $product->supplier->balanceSnapshots->first();
            if (! $snapshot || $snapshot->currency !== $product->currency) {
                return true;
            }

            return $snapshot->available_balance_minor >= $product->cost_minor;
        });

        if ($eligible->isEmpty()) {
            return null;
        }

        // Rank: exact region match first, then priority (lower is higher priority), then reliability desc, then cost asc
        return $eligible->sort(function (SupplierProduct $a, SupplierProduct $b) use ($regionCode): int {
            $aExact = $a->region_code === $regionCode ? 0 : 1;
            $bExact = $b->region_code === $regionCode ? 0 : 1;
            if ($aExact !== $bExact) {
                return $aExact <=> $bExact;
            }

            $aPriority = $a->priority_override ?? $a->supplier->priority;
            $bPriority = $b->priority_override ?? $b->supplier->priority;
            if ($aPriority !== $bPriority) {
                return $aPriority <=> $bPriority;
            }

            $reliabilityCmp = (float) $b->supplier->reliability_score <=> (float) $a->supplier->reliability_score;
            if ($reliabilityCmp !== 0) {
                return $reliabilityCmp;
            }

            return $a->cost_minor <=> $b->cost_minor;
        })->first();
    }
}
