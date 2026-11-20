<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 64)->unique();
            $table->string('name_en', 120);
            $table->string('name_km', 120);
            $table->string('discount_type', 24); // percent, fixed_minor
            $table->bigInteger('discount_value'); // bps or minor units
            $table->string('currency', 3)->default('USD');
            $table->bigInteger('min_order_minor')->default(0);
            $table->bigInteger('max_discount_minor')->nullable();
            $table->unsignedInteger('max_total_uses')->nullable();
            $table->unsignedInteger('max_uses_per_user')->default(1);
            $table->unsignedInteger('used_count')->default(0);
            $table->foreignUuid('game_id')->nullable()->constrained('games')->nullOnDelete();
            $table->boolean('is_active')->default(true)->index();
            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('price_quotes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('game_id')->constrained('games')->restrictOnDelete();
            $table->foreignUuid('game_package_id')->constrained('game_packages')->restrictOnDelete();
            $table->foreignUuid('coupon_id')->nullable()->constrained('coupons')->restrictOnDelete();
            $table->string('region_code', 32);
            $table->string('currency', 3);
            $table->string('payment_method', 32);
            $table->bigInteger('supplier_cost_minor');
            $table->string('supplier_cost_currency', 3)->default('USD');
            $table->bigInteger('subtotal_minor');
            $table->bigInteger('discount_minor')->default(0);
            $table->bigInteger('payment_fee_minor')->default(0);
            $table->bigInteger('total_minor');
            $table->decimal('exchange_rate_usd_to_khr', 14, 4);
            $table->unsignedInteger('package_version')->default(1);
            $table->string('signature', 128);
            $table->timestampTz('expires_at')->index();
            $table->timestampTz('consumed_at')->nullable();
            $table->json('pricing_snapshot');
            $table->timestampsTz();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('order_number', 40)->unique();
            $table->string('tracking_token_hash', 64)->unique();
            $table->string('idempotency_key', 128)->unique();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignUuid('price_quote_id')->unique()->constrained('price_quotes')->restrictOnDelete();
            $table->foreignUuid('game_id')->constrained('games')->restrictOnDelete();
            $table->foreignUuid('game_package_id')->constrained('game_packages')->restrictOnDelete();
            $table->string('region_code', 32);
            $table->string('status', 32)->default('awaiting_payment')->index();
            $table->string('payment_status', 32)->default('pending')->index();
            $table->string('fulfillment_status', 32)->default('not_started')->index();
            $table->string('currency', 3);
            $table->bigInteger('subtotal_minor');
            $table->bigInteger('discount_minor')->default(0);
            $table->bigInteger('payment_fee_minor')->default(0);
            $table->bigInteger('total_minor');
            $table->bigInteger('supplier_cost_minor');
            $table->string('supplier_cost_currency', 3)->default('USD');
            $table->decimal('exchange_rate_usd_to_khr', 14, 4);
            $table->text('player_fields_encrypted');
            $table->string('masked_player_identifier', 120);
            $table->string('customer_email', 180)->nullable()->index();
            $table->string('customer_phone', 32)->nullable();
            $table->string('client_ip', 45)->nullable();
            $table->string('locale', 8)->default('km');
            $table->timestampTz('expires_at')->nullable()->index();
            $table->timestampTz('paid_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->json('financial_snapshot');
            $table->timestampsTz();
            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('orders')->restrictOnDelete();
            $table->foreignUuid('game_package_id')->constrained('game_packages')->restrictOnDelete();
            $table->string('internal_sku', 96);
            $table->string('name_en', 160);
            $table->string('name_km', 160);
            $table->unsignedInteger('quantity')->default(1);
            $table->bigInteger('unit_price_minor');
            $table->bigInteger('line_total_minor');
            $table->string('currency', 3);
            $table->timestampsTz();
        });

        Schema::create('order_status_history', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('orders')->restrictOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->string('actor_type', 32)->default('system'); // system, customer, admin, webhook
            $table->uuid('actor_id')->nullable();
            $table->string('reason', 255)->nullable();
            $table->json('metadata')->nullable();
            $table->timestampTz('created_at')->useCurrent()->index();
        });

        Schema::create('coupon_redemptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('coupon_id')->constrained('coupons')->restrictOnDelete();
            $table->foreignUuid('order_id')->unique()->constrained('orders')->restrictOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->bigInteger('discount_minor');
            $table->string('currency', 3);
            $table->timestampTz('redeemed_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_redemptions');
        Schema::dropIfExists('order_status_history');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('price_quotes');
        Schema::dropIfExists('coupons');
    }
};
