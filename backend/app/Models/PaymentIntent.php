<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentIntent extends Model
{
    use HasUuids;

    protected $fillable = [
        'order_id',
        'provider',
        'provider_mode',
        'provider_reference',
        'merchant_reference',
        'idempotency_key',
        'status',
        'currency',
        'amount_minor',
        'paid_amount_minor',
        'qr_payload',
        'checkout_url',
        'expires_at',
        'verified_at',
        'provider_metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'amount_minor' => 'integer',
            'paid_amount_minor' => 'integer',
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
            'provider_metadata' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(PaymentEvent::class)->orderBy('created_at');
    }

    public function reconciliations(): HasMany
    {
        return $this->hasMany(PaymentReconciliation::class);
    }
}
