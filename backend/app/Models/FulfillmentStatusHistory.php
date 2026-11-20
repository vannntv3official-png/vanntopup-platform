<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FulfillmentStatusHistory extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'fulfillment_status_history';

    protected $fillable = [
        'fulfillment_id',
        'from_status',
        'to_status',
        'actor_type',
        'actor_id',
        'reason',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function fulfillment(): BelongsTo
    {
        return $this->belongsTo(Fulfillment::class);
    }
}
