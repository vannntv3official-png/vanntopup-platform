<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Fulfillment\FulfillmentOrchestrator;
use App\Services\Fulfillment\FulfillmentStateMachine;
use App\Services\Order\OrderStateMachine;
use App\Services\Payment\PaymentService;
use App\Services\Security\AuditService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminOrderController extends Controller
{
    public function __construct(
        private readonly FulfillmentOrchestrator $orchestrator,
        private readonly FulfillmentStateMachine $fulfillmentStateMachine,
        private readonly OrderStateMachine $orderStateMachine,
        private readonly PaymentService $paymentService,
        private readonly AuditService $auditService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Order::query()
            ->with(['game:id,name,slug', 'gamePackage:id,name_en,name_km,internal_sku', 'fulfillment:id,order_id,status,attempt_count,supplier_id'])
            ->latest();

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($search = trim((string) $request->query('q', ''))) {
            $query->where(function ($q) use ($search): void {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhere('customer_email', 'like', "%{$search}%")
                    ->orWhere('masked_player_identifier', 'like', "%{$search}%");
            });
        }

        $orders = $query->paginate(25);

        return response()->json([
            'data' => $orders->items(),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $order = Order::query()
            ->with([
                'game',
                'gamePackage',
                'user:id,name,email',
                'items',
                'statusHistory',
                'paymentIntents.events',
                'fulfillment.attempts.supplier:id,code,name',
                'fulfillment.statusHistory',
                'refunds',
            ])
            ->findOrFail($id);

        return response()->json(['data' => $order]);
    }

    public function retryFulfillment(Request $request, string $id): JsonResponse
    {
        $order = Order::query()->with('fulfillment')->findOrFail($id);
        if (! $order->fulfillment) {
            return response()->json(['message' => 'No fulfillment record exists for this order.'], 422);
        }

        try {
            $updated = $this->orchestrator->retrySafeEligible($order->fulfillment, $request->user()->id);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $this->auditService->log('admin.fulfillment_retried', 'order', $order->id, $request->user()->id, 'admin', null, ['fulfillment_status' => $updated->status->value], null, $request);

        return response()->json(['data' => $updated]);
    }

    public function resolveReview(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'decision' => ['required', 'in:mark_completed,mark_failed'],
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ]);

        $order = Order::query()->with('fulfillment')->findOrFail($id);
        if ($order->status !== OrderStatus::NEEDS_REVIEW) {
            return response()->json(['message' => 'Order is not in needs_review state.'], 422);
        }

        $targetOrder = $validated['decision'] === 'mark_completed' ? OrderStatus::COMPLETED : OrderStatus::FAILED;
        $targetFulfillment = $validated['decision'] === 'mark_completed' ? FulfillmentStatus::SUCCEEDED : FulfillmentStatus::FAILED;

        if ($order->fulfillment && $order->fulfillment->status->canTransitionTo($targetFulfillment)) {
            $this->fulfillmentStateMachine->transition($order->fulfillment, $targetFulfillment, 'admin', $request->user()->id, $validated['reason']);
            $order->fulfillment_status = $targetFulfillment;
            $order->save();
        }

        $this->orderStateMachine->transition($order, $targetOrder, 'admin', $request->user()->id, $validated['reason']);
        $this->auditService->log('admin.order_review_resolved', 'order', $order->id, $request->user()->id, 'admin', null, $validated, null, $request);

        return response()->json(['data' => $order->fresh(['fulfillment', 'statusHistory'])]);
    }

    public function refund(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ]);

        $order = Order::query()->findOrFail($id);

        try {
            $refund = $this->paymentService->requestRefund($order, $validated['reason'], $request->user()->id);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $this->auditService->log('admin.refund_requested', 'order', $order->id, $request->user()->id, 'admin', null, ['refund_id' => $refund->id, 'status' => $refund->status], null, $request);

        return response()->json(['data' => $refund], 201);
    }
}
