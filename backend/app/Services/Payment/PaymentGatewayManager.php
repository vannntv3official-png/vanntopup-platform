<?php

namespace App\Services\Payment;

use App\Services\Payment\Adapters\AbaPayWayAdapter;
use App\Services\Payment\Adapters\MockPayWayAdapter;
use App\Services\Security\ProductionSafetyGuard;
use InvalidArgumentException;

class PaymentGatewayManager
{
    public function __construct(
        private readonly MockPayWayAdapter $mockAdapter,
        private readonly AbaPayWayAdapter $abaAdapter,
        private readonly ProductionSafetyGuard $safetyGuard,
    ) {}

    public function resolve(?string $provider = null): PaymentGatewayInterface
    {
        $code = $provider ?: (string) config('topup.payment.default_provider', 'mock_payway');
        $this->safetyGuard->assertSafeProvider($code, 'payment');

        return match ($code) {
            'mock_payway' => $this->mockAdapter,
            'aba_payway' => $this->abaAdapter,
            default => throw new InvalidArgumentException("Unsupported payment gateway [{$code}]."),
        };
    }
}
