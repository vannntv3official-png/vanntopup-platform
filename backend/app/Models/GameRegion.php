<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class GameRegion extends Model
{
    use HasUuids;

    protected $fillable = ['game_id', 'code', 'name_en', 'name_km', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function packages(): BelongsToMany
    {
        return $this->belongsToMany(GamePackage::class, 'package_regions')
            ->withPivot(['override_selling_price_minor', 'is_available']);
    }
}
