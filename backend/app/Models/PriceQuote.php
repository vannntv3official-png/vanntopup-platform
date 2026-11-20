<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceQuote extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'game_id',
        'game_package_id',
        'coupon_id',
        'region_code',
        'currency',
        'payment_method',
        'supplier_cost_minor',
        'supplier_cost_currency',
        'subtotal_minor',
        'discount_minor',
        'payment_fee_minor',
        'total_minor',
        'exchange_rate_usd_to_khr',
        'package_version',
        'signature',
        'expires_at',
        'consumed_at',
        'pricing_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'supplier_cost_minor' => 'integer',
            'subtotal_minor' => 'integer',
            'discount_minor' => 'integer',
            'payment_fee_minor' => 'integer',
            'total_minor' => 'integer',
            'exchange_rate_usd_to_khr' => 'decimal:4',
            'package_version' => 'integer',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'pricing_snapshot' => 'array',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function gamePackage(): BelongsTo
    {
        return $this->belongsTo(GamePackage::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }
}
