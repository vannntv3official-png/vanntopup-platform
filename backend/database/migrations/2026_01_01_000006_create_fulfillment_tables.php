<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fulfillments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->unique()->constrained('orders')->restrictOnDelete();
            $table->foreignUuid('supplier_id')->nullable()->constrained('suppliers')->restrictOnDelete();
            $table->foreignUuid('supplier_product_id')->nullable()->constrained('supplier_products')->restrictOnDelete();
            $table->string('idempotency_key', 128)->unique();
            $table->string('status', 32)->default('not_started')->index(); // not_started, queued, processing, succeeded, failed, uncertain, needs_review, cancelled
            $table->string('external_reference', 128)->nullable()->index();
            $table->string('supplier_transaction_id', 128)->nullable()->index();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->bigInteger('actual_supplier_cost_minor')->nullable();
            $table->string('actual_supplier_currency', 3)->nullable();
            $table->string('failure_code', 64)->nullable();
            $table->string('failure_reason', 500)->nullable();
            $table->boolean('submission_sent')->default(false);
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->json('supplier_response_redacted')->nullable();
            $table->timestampsTz();
        });

        Schema::create('fulfillment_attempts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('fulfillment_id')->constrained('fulfillments')->restrictOnDelete();
            $table->foreignUuid('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->unsignedInteger('attempt_number');
            $table->string('idempotency_key', 128);
            $table->string('action', 32)->default('submit'); // submit, status_lookup
            $table->string('outcome', 32); // succeeded, pending, failed_definitive, timeout_uncertain, rejected
            $table->string('external_reference', 128)->nullable();
            $table->unsignedInteger('http_status')->nullable();
            $table->unsignedInteger('duration_ms')->default(0);
            $table->string('error_code', 64)->nullable();
            $table->string('error_message', 500)->nullable();
            $table->json('request_redacted')->nullable();
            $table->json('response_redacted')->nullable();
            $table->timestampTz('attempted_at')->useCurrent()->index();
            $table->unique(['fulfillment_id', 'attempt_number']);
        });

        Schema::create('fulfillment_status_history', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('fulfillment_id')->constrained('fulfillments')->restrictOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->string('actor_type', 32)->default('system');
            $table->uuid('actor_id')->nullable();
            $table->string('reason', 255)->nullable();
            $table->json('metadata')->nullable();
            $table->timestampTz('created_at')->useCurrent()->index();
        });

        Schema::create('fulfillment_reconciliations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('fulfillment_id')->constrained('fulfillments')->restrictOnDelete();
            $table->string('status', 32); // matched, supplier_succeeded_local_pending, cost_mismatch, unresolved
            $table->string('supplier_reported_status', 48)->nullable();
            $table->bigInteger('expected_cost_minor')->nullable();
            $table->bigInteger('reported_cost_minor')->nullable();
            $table->text('notes')->nullable();
            $table->timestampTz('reconciled_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fulfillment_reconciliations');
        Schema::dropIfExists('fulfillment_status_history');
        Schema::dropIfExists('fulfillment_attempts');
        Schema::dropIfExists('fulfillments');
    }
};
