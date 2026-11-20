<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FulfillmentAttempt extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'fulfillment_id',
        'supplier_id',
        'attempt_number',
        'idempotency_key',
        'action',
        'outcome',
        'external_reference',
        'http_status',
        'duration_ms',
        'error_code',
        'error_message',
        'request_redacted',
        'response_redacted',
        'attempted_at',
    ];

    protected function casts(): array
    {
        return [
            'attempt_number' => 'integer',
            'http_status' => 'integer',
            'duration_ms' => 'integer',
            'request_redacted' => 'array',
            'response_redacted' => 'array',
            'attempted_at' => 'datetime',
        ];
    }

    public function fulfillment(): BelongsTo
    {
        return $this->belongsTo(Fulfillment::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
