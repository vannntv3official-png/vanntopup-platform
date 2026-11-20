<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerProfile extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'telegram_chat_id_encrypted',
        'notify_email',
        'notify_in_app',
        'notify_telegram',
        'risk_score',
        'is_flagged',
        'saved_player_accounts',
    ];

    protected function casts(): array
    {
        return [
            'telegram_chat_id_encrypted' => 'encrypted',
            'notify_email' => 'boolean',
            'notify_in_app' => 'boolean',
            'notify_telegram' => 'boolean',
            'is_flagged' => 'boolean',
            'saved_player_accounts' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
