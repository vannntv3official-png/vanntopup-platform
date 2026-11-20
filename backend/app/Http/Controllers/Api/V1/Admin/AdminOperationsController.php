<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\PaymentIntent;
use App\Models\ReconciliationDiscrepancy;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Reconciliation\ReconciliationService;
use App\Services\Security\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminOperationsController extends Controller
{
    public function __construct(
        private readonly ReconciliationService $reconciliationService,
        private readonly AuditService $auditService,
    ) {}

    public function payments(): JsonResponse
    {
        $payments = PaymentIntent::query()
            ->with('order:id,order_number,status,currency,total_minor')
            ->latest()
            ->paginate(25);

        return response()->json(['data' => $payments->items()]);
    }

    public function discrepancies(): JsonResponse
    {
        $items = ReconciliationDiscrepancy::query()
            ->with('order:id,order_number,status,payment_status,fulfillment_status,currency,total_minor')
            ->latest('detected_at')
            ->paginate(25);

        return response()->json(['data' => $items->items()]);
    }

    public function runReconciliation(): JsonResponse
    {
        $created = $this->reconciliationService->runAutomatedScan();

        return response()->json([
            'data' => [
                'new_discrepancies_count' => $created->count(),
                'discrepancies' => $created,
            ],
        ]);
    }

    public function updateDiscrepancy(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'resolution_status' => ['required', 'in:open,investigating,resolved,dismissed'],
            'operator_notes' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        $item = ReconciliationDiscrepancy::query()->findOrFail($id);
        $history = $item->audit_history ?? [];
        $history[] = [
            'from' => $item->resolution_status,
            'to' => $validated['resolution_status'],
            'notes' => $validated['operator_notes'],
            'admin_id' => $request->user()->id,
            'at' => now()->toIso8601String(),
        ];

        $item->resolution_status = $validated['resolution_status'];
        $item->operator_notes = $validated['operator_notes'];
        $item->audit_history = $history;
        if (in_array($validated['resolution_status'], ['resolved', 'dismissed'], true)) {
            $item->resolved_by_user_id = $request->user()->id;
            $item->resolved_at = now();
        }
        $item->save();

        $this->auditService->log('admin.discrepancy_updated', 'reconciliation_discrepancy', $item->id, $request->user()->id, 'admin', null, $validated, null, $request);

        return response()->json(['data' => $item]);
    }

    public function coupons(): JsonResponse
    {
        return response()->json(['data' => Coupon::query()->latest()->get()]);
    }

    public function storeCoupon(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:64', 'unique:coupons,code'],
            'name_en' => ['required', 'string', 'max:120'],
            'name_km' => ['required', 'string', 'max:120'],
            'discount_type' => ['required', 'in:percent,fixed_minor'],
            'discount_value' => ['required', 'integer', 'min:1'],
            'currency' => ['required', 'in:USD,KHR'],
            'min_order_minor' => ['nullable', 'integer', 'min:0'],
            'max_discount_minor' => ['nullable', 'integer', 'min:1'],
            'max_total_uses' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $validated['code'] = strtoupper($validated['code']);
        $coupon = Coupon::query()->create($validated);

        return response()->json(['data' => $coupon], 201);
    }

    public function customers(): JsonResponse
    {
        $customers = User::query()
            ->withCount('orders')
            ->with('roles:id,slug')
            ->latest()
            ->paginate(25);

        return response()->json(['data' => $customers->items()]);
    }

    public function settings(): JsonResponse
    {
        return response()->json(['data' => SystemSetting::query()->get()]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fulfillment_global_pause' => ['sometimes', 'boolean'],
            'exchange_rate_usd_to_khr' => ['sometimes', 'numeric', 'min:3000', 'max:6000'],
            'default_currency' => ['sometimes', 'in:USD,KHR'],
        ]);

        foreach ($validated as $key => $value) {
            SystemSetting::setValue($key, $value, 'operations', $key !== 'fulfillment_global_pause', $request->user()->id);
        }

        $this->auditService->log('admin.settings_updated', 'system_settings', 'global', $request->user()->id, 'admin', null, $validated, null, $request);

        return response()->json(['data' => SystemSetting::query()->get()]);
    }
}
