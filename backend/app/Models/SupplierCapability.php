<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierCapability extends Model
{
    use HasUuids;

    protected $fillable = [
        'supplier_id',
        'supports_catalog_sync',
        'supports_pricing_query',
        'supports_player_validation',
        'supports_submit_order',
        'supports_status_lookup',
        'supports_balance_check',
        'supports_webhooks',
        'supports_idempotency',
        'supported_currencies',
        'supported_regions',
    ];

    protected function casts(): array
    {
        return [
            'supports_catalog_sync' => 'boolean',
            'supports_pricing_query' => 'boolean',
            'supports_player_validation' => 'boolean',
            'supports_submit_order' => 'boolean',
            'supports_status_lookup' => 'boolean',
            'supports_balance_check' => 'boolean',
            'supports_webhooks' => 'boolean',
            'supports_idempotency' => 'boolean',
            'supported_currencies' => 'array',
            'supported_regions' => 'array',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
