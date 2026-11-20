<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Payment\PaymentService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function __construct(private readonly PaymentService $paymentService) {}

    public function handlePaymentWebhook(Request $request, string $provider): JsonResponse
    {
        $headers = [];
        foreach ($request->headers->all() as $key => $values) {
            $headers[$key] = (string) ($values[0] ?? '');
        }

        try {
            $result = $this->paymentService->handleWebhook(
                provider: $provider,
                payload: $request->all(),
                headers: $headers,
                rawBody: $request->getContent(),
            );
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }

        return response()->json(['data' => $result]);
    }
}
