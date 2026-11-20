<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RefundEvent extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = ['refund_id', 'from_status', 'to_status', 'payload_redacted', 'created_at'];

    protected function casts(): array
    {
        return [
            'payload_redacted' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function refund(): BelongsTo
    {
        return $this->belongsTo(Refund::class);
    }
}
