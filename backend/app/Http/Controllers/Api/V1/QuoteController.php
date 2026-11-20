<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Currency;
use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\GamePackage;
use App\Services\Pricing\PriceQuoteService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuoteController extends Controller
{
    public function __construct(private readonly PriceQuoteService $quoteService) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'game_id' => ['required', 'uuid'],
            'package_id' => ['required', 'uuid'],
            'region_code' => ['required', 'string', 'max:32'],
            'currency' => ['required', 'in:USD,KHR'],
            'payment_method' => ['nullable', 'in:mock_khqr,aba_khqr,aba_payway'],
            'coupon_code' => ['nullable', 'string', 'max:64'],
        ]);

        $game = Game::query()->findOrFail($validated['game_id']);
        $package = GamePackage::query()->findOrFail($validated['package_id']);

        try {
            $quote = $this->quoteService->createQuote(
                game: $game,
                package: $package,
                regionCode: $validated['region_code'],
                currency: Currency::from($validated['currency']),
                paymentMethod: $validated['payment_method'] ?? 'mock_khqr',
                couponCode: $validated['coupon_code'] ?? null,
                userId: $request->user()?->id,
            );
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => [
                'id' => $quote->id,
                'game_id' => $quote->game_id,
                'package_id' => $quote->game_package_id,
                'region_code' => $quote->region_code,
                'currency' => $quote->currency,
                'subtotal_minor' => $quote->subtotal_minor,
                'discount_minor' => $quote->discount_minor,
                'payment_fee_minor' => $quote->payment_fee_minor,
                'total_minor' => $quote->total_minor,
                'exchange_rate_usd_to_khr' => (float) $quote->exchange_rate_usd_to_khr,
                'coupon_code' => $quote->pricing_snapshot['coupon_code'] ?? null,
                'expires_at' => $quote->expires_at->toIso8601String(),
                'signature' => $quote->signature,
            ],
        ], 201);
    }
}
