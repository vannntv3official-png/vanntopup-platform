<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialAdjustment extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'ledger_transaction_id',
        'created_by_user_id',
        'currency',
        'amount_minor',
        'reason',
        'notes',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
