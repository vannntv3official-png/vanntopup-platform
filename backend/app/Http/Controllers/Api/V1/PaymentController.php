<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PaymentIntent;
use App\Services\Order\OrderService;
use App\Services\Payment\Adapters\MockPayWayAdapter;
use App\Services\Payment\PaymentService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly OrderService $orderService,
        private readonly MockPayWayAdapter $mockAdapter,
    ) {}

    public function createSession(Request $request, string $orderNumber): JsonResponse
    {
        $validated = $request->validate([
            'provider' => ['nullable', 'in:mock_payway,aba_payway'],
            'tracking_token' => ['nullable', 'string', 'min:16'],
        ]);

        $order = Order::query()->where('order_number', $orderNumber)->firstOrFail();

        $token = (string) ($validated['tracking_token'] ?? $request->header('X-Order-Tracking-Token', ''));
        if (! $this->orderService->verifyTrackingAccess($order, $token, $request->user()?->id)) {
            return response()->json(['message' => 'Forbidden: valid tracking token or order ownership required.'], 403);
        }

        try {
            $intent = $this->paymentService->createPaymentSession($order, $validated['provider'] ?? null);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => [
                'payment_intent_id' => $intent->id,
                'provider' => $intent->provider,
                'provider_mode' => $intent->provider_mode,
                'status' => $intent->status->value,
                'amount_minor' => $intent->amount_minor,
                'currency' => $intent->currency,
                'reference' => $intent->provider_reference,
                'qr_payload' => $intent->qr_payload,
                'checkout_url' => $intent->checkout_url,
                'expires_at' => $intent->expires_at->toIso8601String(),
                'instructions_en' => $intent->provider_mode === 'mock'
                    ? 'DEVELOPMENT SIMULATOR: Click Simulate Payment below to send a signed mock webhook to the server.'
                    : 'Scan the KHQR code using ABA Mobile or any Bakong-compatible banking app in Cambodia.',
                'instructions_km' => $intent->provider_mode === 'mock'
                    ? 'មុខងារសាកល្បង (DEV): ចុចប៊ូតុងសាកល្បងការទូទាត់ខាងក្រោមដើម្បីផ្ញើសញ្ញាបញ្ជាក់ទៅម៉ាស៊ីនមេ។'
                    : 'សូមស្កេនកូដ KHQR តាមរយៈកម្មវិធី ABA Mobile ឬធនាគារបាគងណាមួយនៅកម្ពុជា។',
            ],
        ], 201);
    }

    /**
     * DEVELOPMENT / TESTING ONLY: Triggers a deterministic signed webhook against the mock gateway.
     * Blocked in production.
     */
    public function simulateMockOutcome(Request $request, string $paymentIntentId): JsonResponse
    {
        if (app()->environment('production') && ! config('topup.allow_mocks_in_production', false)) {
            return response()->json(['message' => 'Mock payment simulator is disabled in production.'], 403);
        }

        $validated = $request->validate([
            'outcome' => ['required', 'in:paid,failed,expired'],
            'tracking_token' => ['nullable', 'string'],
        ]);

        /** @var PaymentIntent $intent */
        $intent = PaymentIntent::query()->with('order')->findOrFail($paymentIntentId);
        if ($intent->provider !== 'mock_payway') {
            return response()->json(['message' => 'Simulator only supports mock_payway payment intents.'], 422);
        }

        $token = (string) ($validated['tracking_token'] ?? $request->header('X-Order-Tracking-Token', ''));
        if (! $this->orderService->verifyTrackingAccess($intent->order, $token, $request->user()?->id)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $secret = (string) config('topup.payment.mock_webhook_secret');
        $sig = $this->mockAdapter->computeSignature(
            $intent->provider_reference,
            $validated['outcome'],
            $intent->amount_minor,
            $intent->currency,
            $secret,
        );

        $payload = [
            'event_id' => 'sim-'.Str::uuid(),
            'provider_reference' => $intent->provider_reference,
            'status' => $validated['outcome'],
            'amount_minor' => $intent->amount_minor,
            'currency' => $intent->currency,
            'merchant_id' => 'MOCK_MERCHANT_KH',
            'signature' => $sig,
        ];

        $result = $this->paymentService->handleWebhook(
            'mock_payway',
            $payload,
            ['X-Mock-Signature' => $sig],
            json_encode($payload, JSON_THROW_ON_ERROR),
        );

        return response()->json(['data' => $result]);
    }
}
