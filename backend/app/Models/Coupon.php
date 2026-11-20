<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Coupon extends Model
{
    use HasUuids;

    protected $fillable = [
        'code',
        'name_en',
        'name_km',
        'discount_type',
        'discount_value',
        'currency',
        'min_order_minor',
        'max_discount_minor',
        'max_total_uses',
        'max_uses_per_user',
        'used_count',
        'game_id',
        'is_active',
        'starts_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'discount_value' => 'integer',
            'min_order_minor' => 'integer',
            'max_discount_minor' => 'integer',
            'max_total_uses' => 'integer',
            'max_uses_per_user' => 'integer',
            'used_count' => 'integer',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
