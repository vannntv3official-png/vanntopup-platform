<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameField extends Model
{
    use HasUuids;

    protected $fillable = [
        'game_id',
        'key',
        'label_en',
        'label_km',
        'placeholder_en',
        'placeholder_km',
        'field_type',
        'is_required',
        'validation_regex',
        'options',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'options' => 'array',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
