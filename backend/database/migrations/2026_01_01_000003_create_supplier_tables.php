<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 64)->unique(); // mock_supplier, kas_play, unipin
            $table->string('name', 120);
            $table->string('adapter_class', 255);
            $table->string('environment', 24)->default('mock'); // mock, sandbox, live
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_paused')->default(false);
            $table->boolean('is_maintenance')->default(false);
            $table->unsignedInteger('priority')->default(100);
            $table->decimal('reliability_score', 5, 2)->default(100.00);
            $table->string('default_currency', 3)->default('USD');
            $table->string('health_status', 24)->default('healthy'); // healthy, degraded, down
            $table->timestampTz('last_health_check_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('supplier_credentials', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->string('environment', 24)->default('sandbox');
            $table->text('credentials_encrypted');
            $table->boolean('is_active')->default(true);
            $table->timestampTz('rotated_at')->nullable();
            $table->timestampsTz();
            $table->unique(['supplier_id', 'environment']);
        });

        Schema::create('supplier_capabilities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('supplier_id')->unique()->constrained('suppliers')->cascadeOnDelete();
            $table->boolean('supports_catalog_sync')->default(false);
            $table->boolean('supports_pricing_query')->default(false);
            $table->boolean('supports_player_validation')->default(false);
            $table->boolean('supports_submit_order')->default(true);
            $table->boolean('supports_status_lookup')->default(false);
            $table->boolean('supports_balance_check')->default(false);
            $table->boolean('supports_webhooks')->default(false);
            $table->boolean('supports_idempotency')->default(false);
            $table->json('supported_currencies')->nullable();
            $table->json('supported_regions')->nullable();
            $table->timestampsTz();
        });

        Schema::create('supplier_products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignUuid('game_package_id')->constrained('game_packages')->restrictOnDelete();
            $table->string('external_sku', 120);
            $table->string('region_code', 32)->default('GLOBAL');
            $table->string('currency', 3)->default('USD');
            $table->bigInteger('cost_minor');
            $table->boolean('is_authorized')->default(true);
            $table->boolean('is_available')->default(true);
            $table->unsignedInteger('priority_override')->nullable();
            $table->json('provider_metadata')->nullable();
            $table->timestampsTz();
            $table->unique(['supplier_id', 'game_package_id', 'region_code'], 'supplier_pkg_region_unique');
            $table->index(['game_package_id', 'region_code', 'is_authorized', 'is_available'], 'supplier_routing_idx');
        });

        Schema::create('supplier_price_history', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('supplier_product_id')->constrained('supplier_products')->restrictOnDelete();
            $table->bigInteger('previous_cost_minor')->nullable();
            $table->bigInteger('new_cost_minor');
            $table->string('currency', 3);
            $table->string('change_reason', 160)->nullable();
            $table->timestampTz('recorded_at')->useCurrent();
        });

        Schema::create('supplier_health_checks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->string('status', 24);
            $table->unsignedInteger('latency_ms')->default(0);
            $table->string('error_message', 500)->nullable();
            $table->timestampTz('checked_at')->useCurrent()->index();
        });

        Schema::create('supplier_balance_snapshots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->string('currency', 3);
            $table->bigInteger('available_balance_minor');
            $table->bigInteger('reserved_balance_minor')->default(0);
            $table->timestampTz('captured_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_balance_snapshots');
        Schema::dropIfExists('supplier_health_checks');
        Schema::dropIfExists('supplier_price_history');
        Schema::dropIfExists('supplier_products');
        Schema::dropIfExists('supplier_capabilities');
        Schema::dropIfExists('supplier_credentials');
        Schema::dropIfExists('suppliers');
    }
};
