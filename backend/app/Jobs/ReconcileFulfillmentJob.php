<?php

namespace App\Jobs;

use App\Services\Fulfillment\FulfillmentOrchestrator;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ReconcileFulfillmentJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $uniqueFor = 300;

    public function __construct(public readonly string $fulfillmentId)
    {
        $this->onQueue('fulfillment');
    }

    public function uniqueId(): string
    {
        return 'reconcile-fulfillment:'.$this->fulfillmentId;
    }

    public function handle(FulfillmentOrchestrator $orchestrator): void
    {
        $orchestrator->reconcileUncertain($this->fulfillmentId);
    }
}
