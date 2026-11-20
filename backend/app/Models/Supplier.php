<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Supplier extends Model
{
    use HasUuids;

    protected $fillable = [
        'code',
        'name',
        'adapter_class',
        'environment',
        'is_active',
        'is_paused',
        'is_maintenance',
        'priority',
        'reliability_score',
        'default_currency',
        'health_status',
        'last_health_check_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_paused' => 'boolean',
            'is_maintenance' => 'boolean',
            'priority' => 'integer',
            'reliability_score' => 'decimal:2',
            'last_health_check_at' => 'datetime',
        ];
    }

    public function capabilities(): HasOne
    {
        return $this->hasOne(SupplierCapability::class);
    }

    public function credentials(): HasMany
    {
        return $this->hasMany(SupplierCredential::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(SupplierProduct::class);
    }

    public function healthChecks(): HasMany
    {
        return $this->hasMany(SupplierHealthCheck::class);
    }

    public function balanceSnapshots(): HasMany
    {
        return $this->hasMany(SupplierBalanceSnapshot::class);
    }
}
