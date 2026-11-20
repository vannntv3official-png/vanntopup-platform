<?php

namespace App\Services\Fulfillment;

use App\Models\Supplier;
use App\Models\SupplierHealthCheck;

class SupplierHealthMonitor
{
    public function __construct(private readonly SupplierAdapterFactory $adapterFactory) {}

    public function check(Supplier $supplier): SupplierHealthCheck
    {
        $adapter = $this->adapterFactory->forSupplier($supplier);
        $result = $adapter->healthCheck($supplier);

        $status = $result['healthy'] ? 'healthy' : 'down';
        $supplier->health_status = $status;
        $supplier->last_health_check_at = now();
        $supplier->save();

        return SupplierHealthCheck::query()->create([
            'supplier_id' => $supplier->id,
            'status' => $status,
            'latency_ms' => $result['latency_ms'],
            'error_message' => $result['error_message'],
            'checked_at' => now(),
        ]);
    }
}
