<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicket extends Model
{
    use HasUuids;

    protected $fillable = [
        'ticket_number',
        'user_id',
        'order_id',
        'contact_email',
        'subject',
        'status',
        'priority',
        'assigned_admin_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class)->orderBy('created_at');
    }
}
