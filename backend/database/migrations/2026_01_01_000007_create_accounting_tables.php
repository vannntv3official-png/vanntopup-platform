<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 64)->unique(); // e.g. 1000_GATEWAY_RECEIVABLE_USD
            $table->string('name', 140);
            $table->string('account_type', 24); // asset, liability, revenue, expense, equity
            $table->string('currency', 3);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        Schema::create('ledger_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('reference_type', 48); // order_payment, fulfillment_cost, refund, adjustment, fx_conversion
            $table->uuid('reference_id')->index();
            $table->string('idempotency_key', 128)->unique();
            $table->string('currency', 3);
            $table->string('description', 255);
            $table->timestampTz('posted_at')->useCurrent()->index();
            $table->json('metadata')->nullable();
        });

        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('ledger_transaction_id')->constrained('ledger_transactions')->restrictOnDelete();
            $table->foreignUuid('ledger_account_id')->constrained('ledger_accounts')->restrictOnDelete();
            $table->string('direction', 8); // debit, credit
            $table->bigInteger('amount_minor');
            $table->string('currency', 3);
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['ledger_account_id', 'created_at']);
        });

        Schema::create('financial_adjustments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->nullable()->constrained('orders')->restrictOnDelete();
            $table->foreignUuid('ledger_transaction_id')->nullable()->constrained('ledger_transactions')->restrictOnDelete();
            $table->foreignUuid('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('currency', 3);
            $table->bigInteger('amount_minor');
            $table->string('reason', 255);
            $table->text('notes')->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_adjustments');
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('ledger_transactions');
        Schema::dropIfExists('ledger_accounts');
    }
};
