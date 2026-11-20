<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_intents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('orders')->restrictOnDelete();
            $table->string('provider', 48)->index(); // mock_payway, aba_payway
            $table->string('provider_mode', 24)->default('mock'); // mock, sandbox, live
            $table->string('provider_reference', 128)->unique();
            $table->string('merchant_reference', 128)->unique();
            $table->string('idempotency_key', 128)->unique();
            $table->string('status', 32)->default('pending')->index(); // pending, verifying, paid, failed, expired, refunded, partially_refunded
            $table->string('currency', 3);
            $table->bigInteger('amount_minor');
            $table->bigInteger('paid_amount_minor')->nullable();
            $table->text('qr_payload')->nullable();
            $table->string('checkout_url', 512)->nullable();
            $table->timestampTz('expires_at')->index();
            $table->timestampTz('verified_at')->nullable();
            $table->json('provider_metadata')->nullable();
            $table->timestampsTz();
            $table->index(['order_id', 'status']);
        });

        Schema::create('payment_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('payment_intent_id')->constrained('payment_intents')->restrictOnDelete();
            $table->string('event_type', 64); // intent_created, webhook_received, verified, failed, expired, duplicate_webhook
            $table->string('external_event_id', 128)->nullable()->index();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->boolean('signature_valid')->nullable();
            $table->json('payload_redacted')->nullable();
            $table->timestampTz('created_at')->useCurrent()->index();
            $table->unique(['payment_intent_id', 'external_event_id'], 'payment_event_dedup_unique');
        });

        Schema::create('payment_reconciliations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('payment_intent_id')->constrained('payment_intents')->restrictOnDelete();
            $table->string('status', 32); // matched, mismatch, provider_missing, local_missing
            $table->bigInteger('expected_amount_minor');
            $table->bigInteger('provider_amount_minor')->nullable();
            $table->string('provider_status', 48)->nullable();
            $table->text('notes')->nullable();
            $table->timestampTz('reconciled_at')->useCurrent();
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('orders')->restrictOnDelete();
            $table->foreignUuid('payment_intent_id')->constrained('payment_intents')->restrictOnDelete();
            $table->foreignUuid('requested_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('idempotency_key', 128)->unique();
            $table->string('provider_refund_reference', 128)->nullable()->unique();
            $table->string('status', 32)->default('pending')->index(); // pending, processing, succeeded, failed, needs_review
            $table->string('currency', 3);
            $table->bigInteger('amount_minor');
            $table->string('reason', 255);
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('refund_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('refund_id')->constrained('refunds')->restrictOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->json('payload_redacted')->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refund_events');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('payment_reconciliations');
        Schema::dropIfExists('payment_events');
        Schema::dropIfExists('payment_intents');
    }
};
