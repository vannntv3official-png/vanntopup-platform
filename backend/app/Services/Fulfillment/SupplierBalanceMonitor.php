<?php

namespace App\Services\Fulfillment;

use App\Models\Supplier;
use App\Models\SupplierBalanceSnapshot;

class SupplierBalanceMonitor
{
    public function __construct(private readonly SupplierAdapterFactory $adapterFactory) {}

    public function capture(Supplier $supplier): ?SupplierBalanceSnapshot
    {
        $adapter = $this->adapterFactory->forSupplier($supplier);
        $result = $adapter->checkBalance($supplier);

        if (! $result['supported']) {
            return null;
        }

        return SupplierBalanceSnapshot::query()->create([
            'supplier_id' => $supplier->id,
            'currency' => $result['currency'],
            'available_balance_minor' => $result['available_balance_minor'],
            'reserved_balance_minor' => 0,
            'captured_at' => now(),
        ]);
    }
}
