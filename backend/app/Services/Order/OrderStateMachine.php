<?php

namespace App\Services\Order;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use DomainException;

class OrderStateMachine
{
    public function transition(
        Order $order,
        OrderStatus $target,
        string $actorType = 'system',
        ?string $actorId = null,
        ?string $reason = null,
        array $metadata = [],
    ): Order {
        $current = $order->status instanceof OrderStatus
            ? $order->status
            : OrderStatus::from((string) $order->status);

        if ($current === $target) {
            return $order;
        }

        if (! $current->canTransitionTo($target)) {
            throw new DomainException(
                "Invalid order state transition from [{$current->value}] to [{$target->value}]."
            );
        }

        $order->status = $target;
        if ($target === OrderStatus::PAID && $order->paid_at === null) {
            $order->paid_at = now();
        }
        if ($target === OrderStatus::COMPLETED && $order->completed_at === null) {
            $order->completed_at = now();
        }
        $order->save();

        OrderStatusHistory::query()->create([
            'order_id' => $order->id,
            'from_status' => $current->value,
            'to_status' => $target->value,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'reason' => $reason,
            'metadata' => $metadata ?: null,
            'created_at' => now(),
        ]);

        return $order;
    }
}
