<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LedgerTransaction extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'reference_type',
        'reference_id',
        'idempotency_key',
        'currency',
        'description',
        'posted_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'posted_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }
}
