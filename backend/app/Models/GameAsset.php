<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameAsset extends Model
{
    use HasUuids;

    protected $fillable = ['game_id', 'asset_type', 'url', 'alt_text'];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
