<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Currency;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Game;
use App\Models\GamePackage;
use App\Models\Supplier;
use App\Services\Fulfillment\SupplierAdapterFactory;
use App\Services\Pricing\PricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function __construct(
        private readonly PricingService $pricingService,
        private readonly SupplierAdapterFactory $supplierAdapterFactory,
    ) {}

    public function categories(): JsonResponse
    {
        $categories = Category::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return response()->json(['data' => $categories]);
    }

    public function games(Request $request): JsonResponse
    {
        $query = Game::query()
            ->with(['category', 'localizations', 'assets', 'regions' => fn ($q) => $q->where('is_active', true), 'fields'])
            ->where('is_active', true)
            ->orderBy('sort_order');

        if ($search = trim((string) $request->query('q', ''))) {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('publisher', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        if ($category = trim((string) $request->query('category', ''))) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $category));
        }

        if ($request->boolean('featured')) {
            $query->where('is_featured', true);
        }

        $games = $query->get()->map(fn (Game $game) => $this->serializeGame($game, false));

        return response()->json([
            'data' => $games,
            'meta' => [
                'exchange_rate_usd_to_khr' => $this->pricingService->getExchangeRateUsdToKhr(),
            ],
        ]);
    }

    public function showGame(string $slug): JsonResponse
    {
        $game = Game::query()
            ->with([
                'category',
                'localizations',
                'assets',
                'regions' => fn ($q) => $q->where('is_active', true),
                'fields',
                'packages' => fn ($q) => $q->where('is_available', true)->with('regions'),
            ])
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        return response()->json([
            'data' => $this->serializeGame($game, true),
            'meta' => [
                'exchange_rate_usd_to_khr' => $this->pricingService->getExchangeRateUsdToKhr(),
            ],
        ]);
    }

    public function validatePlayer(Request $request, string $slug): JsonResponse
    {
        $game = Game::query()->with(['fields', 'regions'])->where('slug', $slug)->where('is_active', true)->firstOrFail();

        $validated = $request->validate([
            'region_code' => ['required', 'string', 'max:32'],
            'player_fields' => ['required', 'array'],
        ]);

        foreach ($game->fields as $field) {
            $value = trim((string) ($validated['player_fields'][$field->key] ?? ''));
            if ($field->is_required && $value === '') {
                return response()->json([
                    'valid' => false,
                    'supported' => $game->supports_player_validation,
                    'message' => "Field [{$field->label_en}] is required.",
                ], 422);
            }
            if ($value !== '' && $field->validation_regex && ! preg_match($field->validation_regex, $value)) {
                return response()->json([
                    'valid' => false,
                    'supported' => $game->supports_player_validation,
                    'message' => "Invalid format for [{$field->label_en}].",
                ], 422);
            }
        }

        if (! $game->supports_player_validation) {
            return response()->json([
                'valid' => true,
                'supported' => false,
                'player_name' => null,
                'region_code' => $validated['region_code'],
                'message' => 'Format validated; live player lookup not supported for this title.',
            ]);
        }

        $supplier = Supplier::query()
            ->where('is_active', true)
            ->where('is_paused', false)
            ->orderBy('priority')
            ->first();

        if (! $supplier) {
            return response()->json([
                'valid' => false,
                'supported' => false,
                'message' => 'No active supplier available for player verification.',
            ], 503);
        }

        $adapter = $this->supplierAdapterFactory->forSupplier($supplier);
        $result = $adapter->validatePlayer($game, $validated['region_code'], $validated['player_fields']);

        return response()->json([
            'valid' => $result['valid'],
            'supported' => $result['supported'],
            'player_name' => $result['player_name'],
            'region_code' => $validated['region_code'],
            'message' => $result['message'],
            'provider_mode' => $supplier->environment === 'mock' ? 'mock' : 'live',
        ], $result['valid'] ? 200 : 422);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeGame(Game $game, bool $includePackages): array
    {
        $locEn = $game->localizations->firstWhere('locale', 'en');
        $locKm = $game->localizations->firstWhere('locale', 'km');
        $thumb = $game->assets->firstWhere('asset_type', 'thumbnail')?->url ?? '';
        $banner = $game->assets->firstWhere('asset_type', 'banner')?->url ?? '';
        $rate = $this->pricingService->getExchangeRateUsdToKhr();

        $payload = [
            'id' => $game->id,
            'slug' => $game->slug,
            'name' => $game->name,
            'category_slug' => $game->category?->slug ?? 'mobile',
            'publisher' => $game->publisher,
            'short_description_en' => $locEn?->short_description ?? '',
            'short_description_km' => $locKm?->short_description ?? '',
            'description_en' => $locEn?->description ?? '',
            'description_km' => $locKm?->description ?? '',
            'thumbnail_url' => $thumb,
            'banner_url' => $banner,
            'fulfillment_type' => $game->fulfillment_type,
            'supports_player_validation' => $game->supports_player_validation,
            'is_active' => $game->is_active,
            'is_featured' => $game->is_featured,
            'is_in_maintenance' => $game->is_in_maintenance,
            'maintenance_message_en' => $game->maintenance_message_en,
            'maintenance_message_km' => $game->maintenance_message_km,
            'regions' => $game->regions->map(fn ($r) => [
                'id' => $r->id,
                'code' => $r->code,
                'name_en' => $r->name_en,
                'name_km' => $r->name_km,
                'is_active' => $r->is_active,
            ])->values()->all(),
            'fields' => $game->fields->map(fn ($f) => [
                'id' => $f->id,
                'key' => $f->key,
                'label_en' => $f->label_en,
                'label_km' => $f->label_km,
                'placeholder_en' => $f->placeholder_en,
                'placeholder_km' => $f->placeholder_km,
                'field_type' => $f->field_type,
                'is_required' => $f->is_required,
                'validation_regex' => $f->validation_regex,
                'options' => $f->options,
                'sort_order' => $f->sort_order,
            ])->values()->all(),
        ];

        if ($includePackages) {
            $payload['packages'] = $game->packages->map(function (GamePackage $pkg) use ($rate): array {
                $pkgCurr = Currency::from($pkg->currency);
                $usdMinor = $this->pricingService->convertMinorUnits($pkg->selling_price_minor, $pkgCurr, Currency::USD, $rate);
                $khrMinor = $this->pricingService->convertMinorUnits($pkg->selling_price_minor, $pkgCurr, Currency::KHR, $rate);

                return [
                    'id' => $pkg->id,
                    'game_id' => $pkg->game_id,
                    'internal_sku' => $pkg->internal_sku,
                    'name_en' => $pkg->name_en,
                    'name_km' => $pkg->name_km,
                    'entitlement_description_en' => $pkg->entitlement_description_en,
                    'entitlement_description_km' => $pkg->entitlement_description_km,
                    'bonus_label' => $pkg->bonus_label,
                    'currency' => $pkg->currency,
                    'selling_price_minor' => $pkg->selling_price_minor,
                    'display_price_usd_minor' => $usdMinor,
                    'display_price_khr_minor' => $khrMinor,
                    'is_promotion_eligible' => $pkg->is_promotion_eligible,
                    'is_available' => $pkg->is_available,
                    'is_featured' => $pkg->is_featured,
                    'fulfillment_type' => $pkg->fulfillment_type,
                    'region_codes' => $pkg->regions->pluck('code')->values()->all(),
                    'version' => $pkg->version,
                ];
            })->values()->all();
        }

        return $payload;
    }
}
