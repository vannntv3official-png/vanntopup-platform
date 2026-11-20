<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentReconciliation extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'payment_intent_id',
        'status',
        'expected_amount_minor',
        'provider_amount_minor',
        'provider_status',
        'notes',
        'reconciled_at',
    ];

    protected function casts(): array
    {
        return [
            'expected_amount_minor' => 'integer',
            'provider_amount_minor' => 'integer',
            'reconciled_at' => 'datetime',
        ];
    }

    public function paymentIntent(): BelongsTo
    {
        return $this->belongsTo(PaymentIntent::class);
    }
}
