<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FulfillmentReconciliation extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'fulfillment_id',
        'status',
        'supplier_reported_status',
        'expected_cost_minor',
        'reported_cost_minor',
        'notes',
        'reconciled_at',
    ];

    protected function casts(): array
    {
        return [
            'expected_cost_minor' => 'integer',
            'reported_cost_minor' => 'integer',
            'reconciled_at' => 'datetime',
        ];
    }

    public function fulfillment(): BelongsTo
    {
        return $this->belongsTo(Fulfillment::class);
    }
}
