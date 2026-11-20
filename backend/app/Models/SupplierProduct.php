<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierProduct extends Model
{
    use HasUuids;

    protected $fillable = [
        'supplier_id',
        'game_package_id',
        'external_sku',
        'region_code',
        'currency',
        'cost_minor',
        'is_authorized',
        'is_available',
        'priority_override',
        'provider_metadata',
    ];

    protected function casts(): array
    {
        return [
            'cost_minor' => 'integer',
            'is_authorized' => 'boolean',
            'is_available' => 'boolean',
            'priority_override' => 'integer',
            'provider_metadata' => 'array',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function gamePackage(): BelongsTo
    {
        return $this->belongsTo(GamePackage::class);
    }
}
