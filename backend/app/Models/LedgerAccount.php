<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LedgerAccount extends Model
{
    use HasUuids;

    protected $fillable = ['code', 'name', 'account_type', 'currency', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }
}
