<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class OutboxEvent extends Model
{
    use HasUuids;

    protected $fillable = [
        'aggregate_type',
        'aggregate_id',
        'event_type',
        'idempotency_key',
        'payload',
        'status',
        'attempts',
        'available_at',
        'dispatched_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'attempts' => 'integer',
            'available_at' => 'datetime',
            'dispatched_at' => 'datetime',
        ];
    }
}
