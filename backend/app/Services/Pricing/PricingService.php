<?php

namespace App\Services\Pricing;

use App\Enums\Currency;
use App\Models\Coupon;
use App\Models\GamePackage;
use App\Models\SystemSetting;
use InvalidArgumentException;

class PricingService
{
    public function getExchangeRateUsdToKhr(): float
    {
        $configured = SystemSetting::getValue('exchange_rate_usd_to_khr');

        return is_numeric($configured) && (float) $configured > 0
            ? (float) $configured
            : (float) config('topup.exchange_rate_usd_to_khr', 4100.0);
    }

    /**
     * Convert minor units between USD (cents) and KHR (integer Riel) using bcmath.
     */
    public function convertMinorUnits(int $amountMinor, Currency $from, Currency $to, ?float $usdToKhrRate = null): int
    {
        if ($from === $to) {
            return $amountMinor;
        }

        $rate = number_format($usdToKhrRate ?? $this->getExchangeRateUsdToKhr(), 4, '.', '');

        if ($from === Currency::USD && $to === Currency::KHR) {
            // USD cents -> USD major -> KHR Riel (round up to nearest 100 KHR or 1 KHR)
            $usdMajor = bcdiv((string) $amountMinor, '100', 6);
            $khrExact = bcmul($usdMajor, $rate, 4);

            return (int) ceil((float) $khrExact);
        }

        if ($from === Currency::KHR && $to === Currency::USD) {
            $usdMajor = bcdiv((string) $amountMinor, $rate, 6);
            $usdCents = bcmul($usdMajor, '100', 4);

            return (int) round((float) $usdCents);
        }

        throw new InvalidArgumentException("Unsupported currency conversion {$from->value} -> {$to->value}");
    }

    /**
     * Calculate complete pricing breakdown in integer minor units.
     *
     * @return array{
     *   currency: string,
     *   subtotal_minor: int,
     *   discount_minor: int,
     *   payment_fee_minor: int,
     *   total_minor: int,
     *   supplier_cost_minor: int,
     *   supplier_cost_currency: string,
     *   estimated_profit_minor: int,
     *   exchange_rate_usd_to_khr: float
     * }
     */
    public function calculateBreakdown(
        GamePackage $package,
        Currency $targetCurrency,
        string $regionCode,
        string $paymentMethod = 'mock_khqr',
        ?Coupon $coupon = null,
    ): array {
        $rate = $this->getExchangeRateUsdToKhr();
        $packageCurrency = Currency::from($package->currency);

        // Check for region override price
        $regionPivot = $package->regions()
            ->where('code', $regionCode)
            ->first();

        $baseSellingMinor = ($regionPivot && $regionPivot->pivot?->override_selling_price_minor)
            ? (int) $regionPivot->pivot->override_selling_price_minor
            : (int) $package->selling_price_minor;

        // Enforce minimum profit floor relative to base cost
        $minAllowedMinor = (int) $package->base_cost_minor + (int) $package->min_profit_minor;
        $effectivePackagePriceMinor = max($baseSellingMinor, $minAllowedMinor);

        $subtotalMinor = $this->convertMinorUnits($effectivePackagePriceMinor, $packageCurrency, $targetCurrency, $rate);
        $discountMinor = $this->calculateCouponDiscount($package, $coupon, $subtotalMinor, $targetCurrency, $rate);

        $afterDiscount = max(0, $subtotalMinor - $discountMinor);
        $feeBps = (int) config("topup.payment.fee_bps.{$paymentMethod}", 0);
        $paymentFeeMinor = $feeBps > 0 ? (int) round(($afterDiscount * $feeBps) / 10000) : 0;

        $totalMinor = $afterDiscount + $paymentFeeMinor;

        $supplierCostInTarget = $this->convertMinorUnits((int) $package->base_cost_minor, $packageCurrency, $targetCurrency, $rate);
        $estimatedProfitMinor = $afterDiscount - $supplierCostInTarget;

        return [
            'currency' => $targetCurrency->value,
            'subtotal_minor' => $subtotalMinor,
            'discount_minor' => $discountMinor,
            'payment_fee_minor' => $paymentFeeMinor,
            'total_minor' => $totalMinor,
            'supplier_cost_minor' => (int) $package->base_cost_minor,
            'supplier_cost_currency' => $packageCurrency->value,
            'estimated_profit_minor' => $estimatedProfitMinor,
            'exchange_rate_usd_to_khr' => $rate,
        ];
    }

    private function calculateCouponDiscount(
        GamePackage $package,
        ?Coupon $coupon,
        int $subtotalMinor,
        Currency $targetCurrency,
        float $rate,
    ): int {
        if (! $coupon || ! $package->is_promotion_eligible || ! $coupon->is_active) {
            return 0;
        }

        $now = now();
        if ($coupon->starts_at && $now->lt($coupon->starts_at)) {
            return 0;
        }
        if ($coupon->expires_at && $now->gt($coupon->expires_at)) {
            return 0;
        }
        if ($coupon->max_total_uses !== null && $coupon->used_count >= $coupon->max_total_uses) {
            return 0;
        }
        if ($coupon->game_id !== null && $coupon->game_id !== $package->game_id) {
            return 0;
        }

        $couponCurrency = Currency::from($coupon->currency);
        $minOrderTarget = $this->convertMinorUnits((int) $coupon->min_order_minor, $couponCurrency, $targetCurrency, $rate);
        if ($subtotalMinor < $minOrderTarget) {
            return 0;
        }

        if ($coupon->discount_type === 'percent') {
            // discount_value stored in basis points (e.g., 500 = 5.00%)
            $discount = (int) floor(($subtotalMinor * $coupon->discount_value) / 10000);
        } else {
            $discount = $this->convertMinorUnits((int) $coupon->discount_value, $couponCurrency, $targetCurrency, $rate);
        }

        if ($coupon->max_discount_minor !== null) {
            $maxDiscountTarget = $this->convertMinorUnits((int) $coupon->max_discount_minor, $couponCurrency, $targetCurrency, $rate);
            $discount = min($discount, $maxDiscountTarget);
        }

        return min($discount, $subtotalMinor);
    }
}
