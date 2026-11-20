<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierHealthCheck extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = ['supplier_id', 'status', 'latency_ms', 'error_message', 'checked_at'];

    protected function casts(): array
    {
        return [
            'latency_ms' => 'integer',
            'checked_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
