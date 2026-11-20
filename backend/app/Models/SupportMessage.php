<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportMessage extends Model
{
    use HasUuids;

    protected $fillable = [
        'support_ticket_id',
        'sender_user_id',
        'sender_type',
        'message',
        'is_internal_note',
    ];

    protected function casts(): array
    {
        return ['is_internal_note' => 'boolean'];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }
}
