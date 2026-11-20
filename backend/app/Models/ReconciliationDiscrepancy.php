<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReconciliationDiscrepancy extends Model
{
    use HasUuids;

    protected $fillable = [
        'order_id',
        'classification',
        'severity',
        'resolution_status',
        'summary',
        'operator_notes',
        'resolved_by_user_id',
        'detected_at',
        'resolved_at',
        'audit_history',
    ];

    protected function casts(): array
    {
        return [
            'detected_at' => 'datetime',
            'resolved_at' => 'datetime',
            'audit_history' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
