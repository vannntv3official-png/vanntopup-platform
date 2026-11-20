<?php

namespace App\Services\Fulfillment;

use App\Models\Fulfillment;
use App\Models\FulfillmentAttempt;
use App\Services\Security\RedactionService;

class FulfillmentAuditService
{
    public function __construct(private readonly RedactionService $redaction) {}

    public function recordAttempt(
        Fulfillment $fulfillment,
        string $supplierId,
        string $action,
        string $outcome,
        ?string $externalReference = null,
        ?int $httpStatus = null,
        int $durationMs = 0,
        ?string $errorCode = null,
        ?string $errorMessage = null,
        array $requestPayload = [],
        array $responsePayload = [],
    ): FulfillmentAttempt {
        $nextNumber = (int) $fulfillment->attempts()->max('attempt_number') + 1;
        $fulfillment->attempt_count = $nextNumber;
        $fulfillment->save();

        return FulfillmentAttempt::query()->create([
            'fulfillment_id' => $fulfillment->id,
            'supplier_id' => $supplierId,
            'attempt_number' => $nextNumber,
            'idempotency_key' => $fulfillment->idempotency_key,
            'action' => $action,
            'outcome' => $outcome,
            'external_reference' => $externalReference,
            'http_status' => $httpStatus,
            'duration_ms' => $durationMs,
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
            'request_redacted' => $this->redaction->redact($requestPayload),
            'response_redacted' => $this->redaction->redact($responsePayload),
            'attempted_at' => now(),
        ]);
    }
}
