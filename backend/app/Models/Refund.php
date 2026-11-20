<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Refund extends Model
{
    use HasUuids;

    protected $fillable = [
        'order_id',
        'payment_intent_id',
        'requested_by_user_id',
        'idempotency_key',
        'provider_refund_reference',
        'status',
        'currency',
        'amount_minor',
        'reason',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function paymentIntent(): BelongsTo
    {
        return $this->belongsTo(PaymentIntent::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(RefundEvent::class);
    }
}
