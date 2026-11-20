<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierPriceHistory extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'supplier_price_history';

    protected $fillable = [
        'supplier_product_id',
        'previous_cost_minor',
        'new_cost_minor',
        'currency',
        'change_reason',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'previous_cost_minor' => 'integer',
            'new_cost_minor' => 'integer',
            'recorded_at' => 'datetime',
        ];
    }

    public function supplierProduct(): BelongsTo
    {
        return $this->belongsTo(SupplierProduct::class);
    }
}
