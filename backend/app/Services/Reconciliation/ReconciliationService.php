<?php

namespace App\Services\Reconciliation;

use App\Enums\FulfillmentStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\ReconciliationDiscrepancy;
use Illuminate\Support\Collection;

class ReconciliationService
{
    /**
     * Scan for paid-but-not-fulfilled orders and record discrepancies idempotently.
     *
     * @return Collection<int, ReconciliationDiscrepancy>
     */
    public function runAutomatedScan(): Collection
    {
        $created = collect();

        $stuckOrders = Order::query()
            ->where('payment_status', PaymentStatus::PAID->value)
            ->whereIn('fulfillment_status', [
                FulfillmentStatus::NOT_STARTED->value,
                FulfillmentStatus::UNCERTAIN->value,
                FulfillmentStatus::FAILED->value,
                FulfillmentStatus::NEEDS_REVIEW->value,
            ])
            ->where('paid_at', '<', now()->subMinutes(5))
            ->get();

        foreach ($stuckOrders as $order) {
            $exists = ReconciliationDiscrepancy::query()
                ->where('order_id', $order->id)
                ->where('classification', 'paid_not_fulfilled')
                ->whereIn('resolution_status', ['open', 'investigating'])
                ->exists();

            if (! $exists) {
                $created->push(ReconciliationDiscrepancy::query()->create([
                    'order_id' => $order->id,
                    'classification' => 'paid_not_fulfilled',
                    'severity' => 'high',
                    'resolution_status' => 'open',
                    'summary' => "Order {$order->order_number} is paid but fulfillment is in [{$order->fulfillment_status->value}] state.",
                    'detected_at' => now(),
                    'audit_history' => [['event' => 'automated_scan_detected', 'at' => now()->toIso8601String()]],
                ]));
            }
        }

        return $created;
    }
}
