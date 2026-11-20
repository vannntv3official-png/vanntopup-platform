<?php

namespace App\Models;

use App\Enums\FulfillmentStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Fulfillment extends Model
{
    use HasUuids;

    protected $fillable = [
        'order_id',
        'supplier_id',
        'supplier_product_id',
        'idempotency_key',
        'status',
        'external_reference',
        'supplier_transaction_id',
        'attempt_count',
        'actual_supplier_cost_minor',
        'actual_supplier_currency',
        'failure_code',
        'failure_reason',
        'submission_sent',
        'submitted_at',
        'completed_at',
        'supplier_response_redacted',
    ];

    protected function casts(): array
    {
        return [
            'status' => FulfillmentStatus::class,
            'attempt_count' => 'integer',
            'actual_supplier_cost_minor' => 'integer',
            'submission_sent' => 'boolean',
            'submitted_at' => 'datetime',
            'completed_at' => 'datetime',
            'supplier_response_redacted' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function supplierProduct(): BelongsTo
    {
        return $this->belongsTo(SupplierProduct::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(FulfillmentAttempt::class)->orderBy('attempt_number');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(FulfillmentStatusHistory::class)->orderBy('created_at');
    }
}
