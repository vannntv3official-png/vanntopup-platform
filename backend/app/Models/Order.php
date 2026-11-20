<?php

namespace App\Models;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasUuids;

    protected $fillable = [
        'order_number',
        'tracking_token_hash',
        'idempotency_key',
        'user_id',
        'price_quote_id',
        'game_id',
        'game_package_id',
        'region_code',
        'status',
        'payment_status',
        'fulfillment_status',
        'currency',
        'subtotal_minor',
        'discount_minor',
        'payment_fee_minor',
        'total_minor',
        'supplier_cost_minor',
        'supplier_cost_currency',
        'exchange_rate_usd_to_khr',
        'player_fields_encrypted',
        'masked_player_identifier',
        'customer_email',
        'customer_phone',
        'client_ip',
        'locale',
        'expires_at',
        'paid_at',
        'completed_at',
        'financial_snapshot',
    ];

    protected $hidden = [
        'tracking_token_hash',
        'player_fields_encrypted',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'fulfillment_status' => FulfillmentStatus::class,
            'subtotal_minor' => 'integer',
            'discount_minor' => 'integer',
            'payment_fee_minor' => 'integer',
            'total_minor' => 'integer',
            'supplier_cost_minor' => 'integer',
            'exchange_rate_usd_to_khr' => 'decimal:4',
            'player_fields_encrypted' => 'encrypted:array',
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'completed_at' => 'datetime',
            'financial_snapshot' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function gamePackage(): BelongsTo
    {
        return $this->belongsTo(GamePackage::class);
    }

    public function priceQuote(): BelongsTo
    {
        return $this->belongsTo(PriceQuote::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('created_at');
    }

    public function paymentIntents(): HasMany
    {
        return $this->hasMany(PaymentIntent::class)->latest();
    }

    public function fulfillment(): HasOne
    {
        return $this->hasOne(Fulfillment::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }
}
