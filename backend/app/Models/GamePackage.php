<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GamePackage extends Model
{
    use HasUuids;

    protected $fillable = [
        'game_id',
        'internal_sku',
        'name_en',
        'name_km',
        'entitlement_description_en',
        'entitlement_description_km',
        'bonus_label',
        'currency',
        'base_cost_minor',
        'selling_price_minor',
        'min_profit_minor',
        'markup_percent',
        'fulfillment_type',
        'is_promotion_eligible',
        'is_available',
        'is_featured',
        'version',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'base_cost_minor' => 'integer',
            'selling_price_minor' => 'integer',
            'min_profit_minor' => 'integer',
            'markup_percent' => 'decimal:2',
            'is_promotion_eligible' => 'boolean',
            'is_available' => 'boolean',
            'is_featured' => 'boolean',
            'version' => 'integer',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function regions(): BelongsToMany
    {
        return $this->belongsToMany(GameRegion::class, 'package_regions')
            ->withPivot(['override_selling_price_minor', 'is_available']);
    }

    public function supplierProducts(): HasMany
    {
        return $this->hasMany(SupplierProduct::class);
    }
}
