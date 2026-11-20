<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CouponRedemption extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = ['coupon_id', 'order_id', 'user_id', 'discount_minor', 'currency', 'redeemed_at'];

    protected function casts(): array
    {
        return [
            'discount_minor' => 'integer',
            'redeemed_at' => 'datetime',
        ];
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }
}
