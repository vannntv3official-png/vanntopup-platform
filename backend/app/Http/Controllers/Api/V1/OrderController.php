<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Order\OrderService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orderService) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'quote_id' => ['required', 'uuid'],
            'quote_signature' => ['required', 'string', 'min:16'],
            'player_fields' => ['required', 'array'],
            'customer_email' => ['nullable', 'email', 'max:180'],
            'customer_phone' => ['nullable', 'string', 'min:7', 'max:24'],
            'idempotency_key' => ['required', 'string', 'min:8', 'max:128'],
            'locale' => ['nullable', 'in:km,en'],
        ]);

        try {
            $result = $this->orderService->createFromQuote(
                quoteId: $validated['quote_id'],
                quoteSignature: $validated['quote_signature'],
                playerFields: array_map('strval', $validated['player_fields']),
                idempotencyKey: $validated['idempotency_key'],
                userId: $request->user()?->id,
                customerEmail: $validated['customer_email'] ?? $request->user()?->email,
                customerPhone: $validated['customer_phone'] ?? $request->user()?->phone,
                clientIp: $request->ip(),
                locale: $validated['locale'] ?? ($request->user()?->preferred_locale ?? 'km'),
            );
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => $this->serializeOrder($result['order'], $result['tracking_token'] ?: null),
            'reused' => $result['reused'],
        ], $result['reused'] ? 200 : 201);
    }

    public function index(Request $request): JsonResponse
    {
        $orders = Order::query()
            ->with(['game', 'gamePackage'])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => $orders->getCollection()->map(fn (Order $o) => $this->serializeOrder($o))->values(),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    public function track(Request $request, string $orderNumber): JsonResponse
    {
        /** @var Order|null $order */
        $order = Order::query()
            ->with(['game', 'gamePackage', 'statusHistory', 'paymentIntents', 'fulfillment'])
            ->where('order_number', $orderNumber)
            ->first();

        if (! $order) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        $trackingToken = (string) ($request->query('token') ?? $request->header('X-Order-Tracking-Token', ''));
        $userId = $request->user()?->id;

        if (! $this->orderService->verifyTrackingAccess($order, $trackingToken, $userId)) {
            return response()->json(['message' => 'Forbidden: valid tracking token or order ownership required.'], 403);
        }

        return response()->json([
            'data' => $this->serializeOrder($order, null, true),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeOrder(Order $order, ?string $plainTrackingToken = null, bool $includeTimeline = false): array
    {
        $payload = [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status->value,
            'payment_status' => $order->payment_status->value,
            'fulfillment_status' => $order->fulfillment_status->value,
            'game_name' => $order->game?->name ?? '',
            'game_slug' => $order->game?->slug ?? '',
            'package_name_en' => $order->gamePackage?->name_en ?? '',
            'package_name_km' => $order->gamePackage?->name_km ?? '',
            'region_code' => $order->region_code,
            'masked_player_identifier' => $order->masked_player_identifier,
            'currency' => $order->currency,
            'subtotal_minor' => $order->subtotal_minor,
            'discount_minor' => $order->discount_minor,
            'payment_fee_minor' => $order->payment_fee_minor,
            'total_minor' => $order->total_minor,
            'customer_email' => $order->customer_email,
            'customer_phone' => $order->customer_phone,
            'created_at' => $order->created_at?->toIso8601String(),
            'paid_at' => $order->paid_at?->toIso8601String(),
            'completed_at' => $order->completed_at?->toIso8601String(),
            'expires_at' => $order->expires_at?->toIso8601String(),
        ];

        if ($plainTrackingToken !== null) {
            $payload['tracking_token'] = $plainTrackingToken;
        }

        if ($includeTimeline) {
            $payload['timeline'] = $this->buildSafeTimeline($order);
        }

        return $payload;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildSafeTimeline(Order $order): array
    {
        $paid = $order->payment_status->value === 'paid';
        $fulfilling = in_array($order->fulfillment_status->value, ['queued', 'processing', 'uncertain', 'succeeded'], true);
        $completed = $order->fulfillment_status->value === 'succeeded';
        $needsReview = $order->status->value === 'needs_review' || $order->fulfillment_status->value === 'needs_review';
        $failed = in_array($order->status->value, ['failed', 'expired', 'cancelled'], true);

        return [
            [
                'code' => 'order_created',
                'label_en' => 'Order Created',
                'label_km' => 'បានបង្កើតការបញ្ជាទិញ',
                'status' => 'completed',
                'timestamp' => $order->created_at?->toIso8601String(),
            ],
            [
                'code' => 'payment_verified',
                'label_en' => 'Payment Verified',
                'label_km' => 'បានផ្ទៀងផ្ទាត់ការទូទាត់',
                'status' => $paid ? 'completed' : ($failed ? 'failed' : 'active'),
                'timestamp' => $order->paid_at?->toIso8601String(),
            ],
            [
                'code' => 'automatic_topup',
                'label_en' => 'Automatic Top-Up',
                'label_km' => 'បញ្ចូលទឹកប្រាក់ហ្គេមដោយស្វ័យប្រវត្តិ',
                'status' => $completed ? 'completed' : ($needsReview ? 'warning' : ($fulfilling ? 'active' : 'pending')),
                'timestamp' => $order->fulfillment?->submitted_at?->toIso8601String(),
            ],
            [
                'code' => 'completed',
                'label_en' => $needsReview ? 'Under Support Verification' : 'Delivered',
                'label_km' => $needsReview ? 'កំពុងត្រួតពិនិត្យដោយក្រុមជំនួយ' : 'បានបញ្ចូលជោគជ័យ',
                'status' => $completed ? 'completed' : ($needsReview ? 'warning' : ($failed ? 'failed' : 'pending')),
                'timestamp' => $order->completed_at?->toIso8601String(),
            ],
        ];
    }
}
