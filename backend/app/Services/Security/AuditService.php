<?php

namespace App\Services\Security;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditService
{
    public function __construct(private readonly RedactionService $redaction) {}

    public function log(
        string $action,
        ?string $targetType = null,
        ?string $targetId = null,
        ?string $actorUserId = null,
        string $actorType = 'system',
        ?array $beforeState = null,
        ?array $afterState = null,
        ?array $metadata = null,
        ?Request $request = null,
    ): AuditLog {
        return AuditLog::query()->create([
            'actor_user_id' => $actorUserId,
            'actor_type' => $actorType,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'before_state' => $beforeState ? $this->redaction->redact($beforeState) : null,
            'after_state' => $afterState ? $this->redaction->redact($afterState) : null,
            'metadata' => $metadata ? $this->redaction->redact($metadata) : null,
            'created_at' => now(),
        ]);
    }
}
