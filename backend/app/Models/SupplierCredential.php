<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierCredential extends Model
{
    use HasUuids;

    protected $fillable = ['supplier_id', 'environment', 'credentials_encrypted', 'is_active', 'rotated_at'];

    protected $hidden = ['credentials_encrypted'];

    protected function casts(): array
    {
        return [
            'credentials_encrypted' => 'encrypted:array',
            'is_active' => 'boolean',
            'rotated_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
