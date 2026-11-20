<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentEvent extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'payment_intent_id',
        'event_type',
        'external_event_id',
        'from_status',
        'to_status',
        'signature_valid',
        'payload_redacted',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'signature_valid' => 'boolean',
            'payload_redacted' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function paymentIntent(): BelongsTo
    {
        return $this->belongsTo(PaymentIntent::class);
    }
}
