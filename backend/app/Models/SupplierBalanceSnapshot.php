<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierBalanceSnapshot extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'supplier_id',
        'currency',
        'available_balance_minor',
        'reserved_balance_minor',
        'captured_at',
    ];

    protected function casts(): array
    {
        return [
            'available_balance_minor' => 'integer',
            'reserved_balance_minor' => 'integer',
            'captured_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
