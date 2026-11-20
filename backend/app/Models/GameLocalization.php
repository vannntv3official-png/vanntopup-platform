<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameLocalization extends Model
{
    use HasUuids;

    protected $fillable = ['game_id', 'locale', 'short_description', 'description', 'instructions'];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
