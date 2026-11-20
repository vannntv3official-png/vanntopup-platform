<?php

namespace App\Services\Fulfillment;

use App\Enums\FulfillmentStatus;
use App\Models\Fulfillment;
use App\Models\FulfillmentStatusHistory;
use DomainException;

class FulfillmentStateMachine
{
    public function transition(
        Fulfillment $fulfillment,
        FulfillmentStatus $target,
        string $actorType = 'system',
        ?string $actorId = null,
        ?string $reason = null,
        array $metadata = [],
    ): Fulfillment {
        $current = $fulfillment->status instanceof FulfillmentStatus
            ? $fulfillment->status
            : FulfillmentStatus::from((string) $fulfillment->status);

        if ($current === $target) {
            return $fulfillment;
        }

        if (! $current->canTransitionTo($target)) {
            throw new DomainException(
                "Invalid fulfillment state transition from [{$current->value}] to [{$target->value}]."
            );
        }

        $fulfillment->status = $target;
        if ($target === FulfillmentStatus::SUCCEEDED && $fulfillment->completed_at === null) {
            $fulfillment->completed_at = now();
        }
        $fulfillment->save();

        FulfillmentStatusHistory::query()->create([
            'fulfillment_id' => $fulfillment->id,
            'from_status' => $current->value,
            'to_status' => $target->value,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'reason' => $reason,
            'metadata' => $metadata ?: null,
            'created_at' => now(),
        ]);

        return $fulfillment;
    }
}
