<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LedgerEntry extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'ledger_transaction_id',
        'ledger_account_id',
        'direction',
        'amount_minor',
        'currency',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(LedgerTransaction::class, 'ledger_transaction_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'ledger_account_id');
    }
}
