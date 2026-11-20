<?php

namespace App\Services\Pricing;

use App\Enums\Currency;
use App\Models\Coupon;
use App\Models\Game;
use App\Models\GamePackage;
use App\Models\PriceQuote;
use DomainException;

class PriceQuoteService
{
    public function __construct(private readonly PricingService $pricingService) {}

    public function createQuote(
        Game $game,
        GamePackage $package,
        string $regionCode,
        Currency $currency,
        string $paymentMethod = 'mock_khqr',
        ?string $couponCode = null,
        ?string $userId = null,
    ): PriceQuote {
        if (! $game->is_active || $game->is_in_maintenance) {
            throw new DomainException('Selected game is currently unavailable or under maintenance.');
        }

        if ($package->game_id !== $game->id || ! $package->is_available) {
            throw new DomainException('Selected package is not available for this game.');
        }

        $regionSupported = $game->regions()->where('code', $regionCode)->where('is_active', true)->exists();
        if (! $regionSupported) {
            throw new DomainException("Region [{$regionCode}] is not supported for this game.");
        }

        $coupon = null;
        if ($couponCode !== null && trim($couponCode) !== '') {
            $coupon = Coupon::query()
                ->whereRaw('LOWER(code) = ?', [strtolower(trim($couponCode))])
                ->where('is_active', true)
                ->first();

            if (! $coupon) {
                throw new DomainException('Promotion code is invalid or expired.');
            }
        }

        $breakdown = $this->pricingService->calculateBreakdown(
            $package,
            $currency,
            $regionCode,
            $paymentMethod,
            $coupon,
        );

        $ttl = (int) config('topup.quote_ttl_seconds', 900);
        $expiresAt = now()->addSeconds($ttl);

        $quote = new PriceQuote([
            'user_id' => $userId,
            'game_id' => $game->id,
            'game_package_id' => $package->id,
            'coupon_id' => $breakdown['discount_minor'] > 0 ? $coupon?->id : null,
            'region_code' => $regionCode,
            'currency' => $breakdown['currency'],
            'payment_method' => $paymentMethod,
            'supplier_cost_minor' => $breakdown['supplier_cost_minor'],
            'supplier_cost_currency' => $breakdown['supplier_cost_currency'],
            'subtotal_minor' => $breakdown['subtotal_minor'],
            'discount_minor' => $breakdown['discount_minor'],
            'payment_fee_minor' => $breakdown['payment_fee_minor'],
            'total_minor' => $breakdown['total_minor'],
            'exchange_rate_usd_to_khr' => $breakdown['exchange_rate_usd_to_khr'],
            'package_version' => $package->version,
            'expires_at' => $expiresAt,
            'pricing_snapshot' => [
                'game_slug' => $game->slug,
                'package_sku' => $package->internal_sku,
                'package_name_en' => $package->name_en,
                'package_name_km' => $package->name_km,
                'coupon_code' => $breakdown['discount_minor'] > 0 ? $coupon?->code : null,
                'estimated_profit_minor' => $breakdown['estimated_profit_minor'],
            ],
        ]);

        $quote->id = (string) \Illuminate\Support\Str::uuid();
        $quote->signature = $this->signQuote($quote);
        $quote->save();

        return $quote;
    }

    public function verifyQuoteForConsumption(PriceQuote $quote, string $providedSignature): void
    {
        if (! hash_equals($this->signQuote($quote), $providedSignature) || ! hash_equals($quote->signature, $providedSignature)) {
            throw new DomainException('Quote signature verification failed.');
        }

        if ($quote->consumed_at !== null) {
            throw new DomainException('Price quote has already been consumed.');
        }

        if ($quote->expires_at->isPast()) {
            throw new DomainException('Price quote has expired. Please request a fresh quote.');
        }
    }

    public function signQuote(PriceQuote $quote): string
    {
        $payload = implode('|', [
            $quote->id,
            $quote->game_id,
            $quote->game_package_id,
            $quote->region_code,
            $quote->currency,
            $quote->subtotal_minor,
            $quote->discount_minor,
            $quote->payment_fee_minor,
            $quote->total_minor,
            $quote->package_version,
            $quote->expires_at->getTimestamp(),
        ]);

        return hash_hmac('sha256', $payload, (string) config('topup.quote_signing_secret'));
    }
}
