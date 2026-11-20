<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\ReconciliationDiscrepancy;
use App\Models\Supplier;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;

class AdminDashboardController extends Controller
{
    public function overview(): JsonResponse
    {
        $paidOrders = Order::query()->where('payment_status', PaymentStatus::PAID->value);
        $revenueUsdMinor = (int) (clone $paidOrders)->where('currency', 'USD')->sum('total_minor');
        $revenueKhrMinor = (int) (clone $paidOrders)->where('currency', 'KHR')->sum('total_minor');

        $completedOrders = Order::query()->where('status', OrderStatus::COMPLETED->value)->get();
        $grossProfitUsdMinor = 0;
        foreach ($completedOrders as $order) {
            if ($order->currency === 'USD' && $order->supplier_cost_currency === 'USD') {
                $grossProfitUsdMinor += ($order->subtotal_minor - $order->discount_minor - $order->supplier_cost_minor);
            }
        }

        $suppliers = Supplier::query()->get(['id', 'code', 'name', 'environment', 'is_active', 'is_paused', 'health_status', 'reliability_score']);

        return response()->json([
            'data' => [
                'metrics' => [
                    'total_orders' => Order::query()->count(),
                    'paid_orders' => (clone $paidOrders)->count(),
                    'successful_fulfillments' => Order::query()->where('fulfillment_status', FulfillmentStatus::SUCCEEDED->value)->count(),
                    'failed_orders' => Order::query()->where('status', OrderStatus::FAILED->value)->count(),
                    'pending_orders' => Order::query()->whereIn('status', [OrderStatus::AWAITING_PAYMENT->value, OrderStatus::FULFILLING->value])->count(),
                    'needs_review_orders' => Order::query()->where('status', OrderStatus::NEEDS_REVIEW->value)->count(),
                    'open_discrepancies' => ReconciliationDiscrepancy::query()->whereIn('resolution_status', ['open', 'investigating'])->count(),
                    'revenue_usd_minor' => $revenueUsdMinor,
                    'revenue_khr_minor' => $revenueKhrMinor,
                    'gross_profit_usd_minor' => $grossProfitUsdMinor,
                ],
                'controls' => [
                    'fulfillment_global_pause' => (bool) SystemSetting::getValue('fulfillment_global_pause', false),
                    'exchange_rate_usd_to_khr' => (float) SystemSetting::getValue('exchange_rate_usd_to_khr', config('topup.exchange_rate_usd_to_khr')),
                ],
                'suppliers' => $suppliers,
            ],
        ]);
    }

    public function auditLogs(): JsonResponse
    {
        $logs = AuditLog::query()
            ->with('actor:id,name,email')
            ->latest('created_at')
            ->limit(100)
            ->get();

        return response()->json(['data' => $logs]);
    }
}
