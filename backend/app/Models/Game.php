<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Game extends Model
{
    use HasUuids;

    protected $fillable = [
        'category_id',
        'slug',
        'name',
        'publisher',
        'fulfillment_type',
        'supports_player_validation',
        'is_active',
        'is_featured',
        'is_in_maintenance',
        'maintenance_message_en',
        'maintenance_message_km',
        'maintenance_starts_at',
        'maintenance_ends_at',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'supports_player_validation' => 'boolean',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'is_in_maintenance' => 'boolean',
            'maintenance_starts_at' => 'datetime',
            'maintenance_ends_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function localizations(): HasMany
    {
        return $this->hasMany(GameLocalization::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(GameAsset::class);
    }

    public function regions(): HasMany
    {
        return $this->hasMany(GameRegion::class)->orderBy('sort_order');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(GameField::class)->orderBy('sort_order');
    }

    public function packages(): HasMany
    {
        return $this->hasMany(GamePackage::class)->orderBy('sort_order');
    }
}
