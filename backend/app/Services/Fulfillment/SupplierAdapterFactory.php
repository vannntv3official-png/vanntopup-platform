<?php

namespace App\Services\Fulfillment;

use App\Models\Supplier;
use App\Services\Fulfillment\Adapters\KasPlayAdapter;
use App\Services\Fulfillment\Adapters\MockSupplierAdapter;
use App\Services\Fulfillment\Adapters\UniPinAdapter;
use App\Services\Security\ProductionSafetyGuard;
use InvalidArgumentException;

class SupplierAdapterFactory
{
    public function __construct(
        private readonly MockSupplierAdapter $mockAdapter,
        private readonly KasPlayAdapter $kasPlayAdapter,
        private readonly UniPinAdapter $uniPinAdapter,
        private readonly ProductionSafetyGuard $safetyGuard,
    ) {}

    public function forSupplier(Supplier $supplier): TopUpSupplierInterface
    {
        $this->safetyGuard->assertSafeProvider($supplier->code, 'supplier');

        return match ($supplier->code) {
            'mock_supplier' => $this->mockAdapter,
            'kas_play' => $this->kasPlayAdapter,
            'unipin' => $this->uniPinAdapter,
            default => throw new InvalidArgumentException("Unknown supplier adapter [{$supplier->code}]."),
        };
    }
}
