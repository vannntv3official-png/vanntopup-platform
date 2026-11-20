<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\GamePackage;
use App\Services\Security\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminCatalogController extends Controller
{
    public function __construct(private readonly AuditService $auditService) {}

    public function games(): JsonResponse
    {
        $games = Game::query()
            ->with(['category', 'regions', 'fields', 'packages'])
            ->orderBy('sort_order')
            ->get();

        return response()->json(['data' => $games]);
    }

    public function updateGame(Request $request, string $id): JsonResponse
    {
        $game = Game::query()->findOrFail($id);
        $before = $game->toArray();

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:160'],
            'is_active' => ['sometimes', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],
            'is_in_maintenance' => ['sometimes', 'boolean'],
            'maintenance_message_en' => ['sometimes', 'nullable', 'string', 'max:255'],
            'maintenance_message_km' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $game->fill($validated);
        $game->save();

        $this->auditService->log('admin.game_updated', 'game', $game->id, $request->user()->id, 'admin', $before, $game->toArray(), null, $request);

        return response()->json(['data' => $game]);
    }

    public function updatePackage(Request $request, string $id): JsonResponse
    {
        $package = GamePackage::query()->findOrFail($id);
        $before = $package->toArray();

        $validated = $request->validate([
            'name_en' => ['sometimes', 'string', 'max:160'],
            'name_km' => ['sometimes', 'string', 'max:160'],
            'base_cost_minor' => ['sometimes', 'integer', 'min:1'],
            'selling_price_minor' => ['sometimes', 'integer', 'min:1'],
            'min_profit_minor' => ['sometimes', 'integer', 'min:0'],
            'is_available' => ['sometimes', 'boolean'],
            'is_promotion_eligible' => ['sometimes', 'boolean'],
        ]);

        $package->fill($validated);
        $package->version = $package->version + 1;
        $package->save();

        $this->auditService->log('admin.package_updated', 'game_package', $package->id, $request->user()->id, 'admin', $before, $package->toArray(), null, $request);

        return response()->json(['data' => $package]);
    }
}
