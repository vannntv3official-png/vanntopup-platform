<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminSession extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'token_id',
        'ip_address',
        'user_agent',
        'mfa_verified',
        'step_up_verified_at',
        'last_active_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'mfa_verified' => 'boolean',
            'step_up_verified_at' => 'datetime',
            'last_active_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
