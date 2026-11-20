<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbox_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('aggregate_type', 64);
            $table->uuid('aggregate_id')->index();
            $table->string('event_type', 96)->index();
            $table->string('idempotency_key', 128)->unique();
            $table->json('payload');
            $table->string('status', 24)->default('pending')->index(); // pending, dispatched, failed
            $table->unsignedInteger('attempts')->default(0);
            $table->timestampTz('available_at')->useCurrent();
            $table->timestampTz('dispatched_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestampsTz();
            $table->index(['status', 'available_at']);
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('order_id')->nullable()->constrained('orders')->restrictOnDelete();
            $table->string('channel', 24); // in_app, email, telegram
            $table->string('event_type', 64);
            $table->string('idempotency_key', 128)->unique();
            $table->string('recipient_masked', 180)->nullable();
            $table->string('subject', 180);
            $table->text('body');
            $table->string('delivery_status', 24)->default('queued')->index(); // queued, sent, failed, read
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('read_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestampsTz();
        });

        Schema::create('support_tickets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('ticket_number', 32)->unique();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('order_id')->nullable()->constrained('orders')->restrictOnDelete();
            $table->string('contact_email', 180)->nullable();
            $table->string('subject', 180);
            $table->string('status', 24)->default('open')->index(); // open, in_progress, resolved, closed
            $table->string('priority', 24)->default('normal'); // low, normal, high, urgent
            $table->foreignUuid('assigned_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });

        Schema::create('support_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('support_ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->foreignUuid('sender_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('sender_type', 24); // customer, guest, admin, system
            $table->text('message');
            $table->boolean('is_internal_note')->default(false);
            $table->timestampsTz();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('actor_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('actor_type', 32)->default('system');
            $table->string('action', 96)->index();
            $table->string('target_type', 64)->nullable();
            $table->string('target_id', 64)->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('before_state')->nullable();
            $table->json('after_state')->nullable();
            $table->json('metadata')->nullable();
            $table->timestampTz('created_at')->useCurrent()->index();
        });

        Schema::create('system_settings', function (Blueprint $table) {
            $table->string('key', 96)->primary();
            $table->json('value');
            $table->string('group', 48)->default('general')->index();
            $table->boolean('is_public')->default(false);
            $table->foreignUuid('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });

        Schema::create('scheduled_job_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('job_name', 120)->index();
            $table->string('status', 24); // running, succeeded, failed
            $table->timestampTz('started_at');
            $table->timestampTz('finished_at')->nullable();
            $table->unsignedInteger('items_processed')->default(0);
            $table->text('error_message')->nullable();
        });

        Schema::create('reconciliation_discrepancies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->nullable()->constrained('orders')->restrictOnDelete();
            $table->string('classification', 64)->index(); // paid_not_fulfilled, fulfilled_payment_mismatch, duplicate_payment, supplier_completed_local_pending, refund_after_fulfillment, supplier_cost_mismatch
            $table->string('severity', 24)->default('high')->index(); // low, medium, high, critical
            $table->string('resolution_status', 24)->default('open')->index(); // open, investigating, resolved, dismissed
            $table->text('summary');
            $table->text('operator_notes')->nullable();
            $table->foreignUuid('resolved_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('detected_at')->useCurrent()->index();
            $table->timestampTz('resolved_at')->nullable();
            $table->json('audit_history')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_discrepancies');
        Schema::dropIfExists('scheduled_job_runs');
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('support_messages');
        Schema::dropIfExists('support_tickets');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('outbox_events');
    }
};
