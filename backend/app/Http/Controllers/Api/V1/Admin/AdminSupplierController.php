<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Services\Fulfillment\SupplierBalanceMonitor;
use App\Services\Fulfillment\SupplierHealthMonitor;
use App\Services\Security\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSupplierController extends Controller
{
    public function __construct(
        private readonly SupplierHealthMonitor $healthMonitor,
        private readonly SupplierBalanceMonitor $balanceMonitor,
        private readonly AuditService $auditService,
    ) {}

    public function index(): JsonResponse
    {
        $suppliers = Supplier::query()
            ->with([
                'capabilities',
                'balanceSnapshots' => fn ($q) => $q->latest('captured_at')->limit(1),
                'healthChecks' => fn ($q) => $q->latest('checked_at')->limit(5),
            ])
            ->orderBy('priority')
            ->get();

        return response()->json(['data' => $suppliers]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $supplier = Supplier::query()->findOrFail($id);
        $before = $supplier->toArray();

        $validated = $request->validate([
            'is_active' => ['sometimes', 'boolean'],
            'is_paused' => ['sometimes', 'boolean'],
            'is_maintenance' => ['sometimes', 'boolean'],
            'priority' => ['sometimes', 'integer', 'min:1', 'max:1000'],
        ]);

        $supplier->fill($validated);
        $supplier->save();

        $this->auditService->log('admin.supplier_updated', 'supplier', $supplier->id, $request->user()->id, 'admin', $before, $supplier->toArray(), null, $request);

        return response()->json(['data' => $supplier]);
    }

    public function runHealthCheck(string $id): JsonResponse
    {
        $supplier = Supplier::query()->findOrFail($id);
        $health = $this->healthMonitor->check($supplier);
        $balance = $this->balanceMonitor->capture($supplier);

        return response()->json([
            'data' => [
                'supplier' => $supplier->fresh(),
                'health_check' => $health,
                'balance_snapshot' => $balance,
            ],
        ]);
    }
}
