<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Services\Order\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SupportController extends Controller
{
    public function __construct(private readonly OrderService $orderService) {}

    public function index(Request $request): JsonResponse
    {
        $tickets = SupportTicket::query()
            ->with(['messages' => fn ($q) => $q->where('is_internal_note', false)])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(20);

        return response()->json(['data' => $tickets->items()]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_number' => ['nullable', 'string', 'max:64'],
            'tracking_token' => ['nullable', 'string', 'max:128'],
            'subject' => ['required', 'string', 'min:4', 'max:180'],
            'message' => ['required', 'string', 'min:10', 'max:4000'],
            'contact_email' => ['nullable', 'email', 'max:180'],
        ]);

        $order = null;
        if (! empty($validated['order_number'])) {
            $order = Order::query()->where('order_number', $validated['order_number'])->first();
            if ($order && ! $this->orderService->verifyTrackingAccess($order, $validated['tracking_token'] ?? null, $request->user()?->id)) {
                return response()->json(['message' => 'Forbidden: cannot attach ticket to unverified order.'], 403);
            }
        }

        $ticket = SupportTicket::query()->create([
            'ticket_number' => 'SUP-'.now()->format('Ymd').'-'.strtoupper(Str::random(5)),
            'user_id' => $request->user()?->id,
            'order_id' => $order?->id,
            'contact_email' => $validated['contact_email'] ?? $request->user()?->email ?? $order?->customer_email,
            'subject' => $validated['subject'],
            'status' => 'open',
            'priority' => $order && $order->status->value === 'needs_review' ? 'high' : 'normal',
        ]);

        SupportMessage::query()->create([
            'support_ticket_id' => $ticket->id,
            'sender_user_id' => $request->user()?->id,
            'sender_type' => $request->user() ? 'customer' : 'guest',
            'message' => $validated['message'],
            'is_internal_note' => false,
        ]);

        return response()->json(['data' => $ticket->load('messages')], 201);
    }
}
