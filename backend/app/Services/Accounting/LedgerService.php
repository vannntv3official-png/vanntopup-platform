<?php

namespace App\Services\Accounting;

use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Models\LedgerTransaction;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class LedgerService
{
    /**
     * Post an immutable, strictly balanced double-entry journal transaction in a single currency.
     *
     * @param  list<array{account_code: string, direction: 'debit'|'credit', amount_minor: int}>  $lines
     * @param  array<string, mixed>  $metadata
     */
    public function postTransaction(
        string $referenceType,
        string $referenceId,
        string $idempotencyKey,
        string $currency,
        string $description,
        array $lines,
        array $metadata = [],
    ): LedgerTransaction {
        if (count($lines) < 2) {
            throw new InvalidArgumentException('A ledger transaction requires at least two entries.');
        }

        $debitSum = 0;
        $creditSum = 0;

        foreach ($lines as $line) {
            $amount = (int) $line['amount_minor'];
            if ($amount <= 0) {
                throw new InvalidArgumentException('Ledger entry amount must be a positive integer.');
            }
            if ($line['direction'] === 'debit') {
                $debitSum += $amount;
            } elseif ($line['direction'] === 'credit') {
                $creditSum += $amount;
            } else {
                throw new InvalidArgumentException("Invalid ledger direction [{$line['direction']}].");
            }
        }

        if ($debitSum !== $creditSum) {
            throw new InvalidArgumentException(
                "Unbalanced ledger transaction: debits ({$debitSum}) != credits ({$creditSum}) for {$currency}."
            );
        }

        return DB::transaction(function () use ($referenceType, $referenceId, $idempotencyKey, $currency, $description, $lines, $metadata): LedgerTransaction {
            $existing = LedgerTransaction::query()
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing) {
                return $existing->load('entries');
            }

            $transaction = LedgerTransaction::query()->create([
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'idempotency_key' => $idempotencyKey,
                'currency' => $currency,
                'description' => $description,
                'posted_at' => now(),
                'metadata' => $metadata,
            ]);

            foreach ($lines as $line) {
                $account = LedgerAccount::query()
                    ->where('code', $line['account_code'])
                    ->where('currency', $currency)
                    ->firstOrFail();

                LedgerEntry::query()->create([
                    'ledger_transaction_id' => $transaction->id,
                    'ledger_account_id' => $account->id,
                    'direction' => $line['direction'],
                    'amount_minor' => (int) $line['amount_minor'],
                    'currency' => $currency,
                    'created_at' => now(),
                ]);
            }

            return $transaction->load('entries');
        });
    }
}
