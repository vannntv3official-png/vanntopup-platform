<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug', 64)->unique();
            $table->string('name_en', 120);
            $table->string('name_km', 120);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        Schema::create('games', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('category_id')->constrained('categories')->restrictOnDelete();
            $table->string('slug', 96)->unique();
            $table->string('name', 160);
            $table->string('publisher', 120);
            $table->string('fulfillment_type', 32)->default('direct_topup');
            $table->boolean('supports_player_validation')->default(true);
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_featured')->default(false)->index();
            $table->boolean('is_in_maintenance')->default(false);
            $table->string('maintenance_message_en')->nullable();
            $table->string('maintenance_message_km')->nullable();
            $table->timestampTz('maintenance_starts_at')->nullable();
            $table->timestampTz('maintenance_ends_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestampsTz();
        });

        Schema::create('game_localizations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('game_id')->constrained('games')->cascadeOnDelete();
            $table->string('locale', 8);
            $table->string('short_description', 280);
            $table->text('description');
            $table->text('instructions')->nullable();
            $table->timestampsTz();
            $table->unique(['game_id', 'locale']);
        });

        Schema::create('game_assets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('game_id')->constrained('games')->cascadeOnDelete();
            $table->string('asset_type', 32); // thumbnail, banner, icon
            $table->string('url', 512);
            $table->string('alt_text', 180)->nullable();
            $table->timestampsTz();
            $table->unique(['game_id', 'asset_type']);
        });

        Schema::create('game_regions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('game_id')->constrained('games')->cascadeOnDelete();
            $table->string('code', 32);
            $table->string('name_en', 120);
            $table->string('name_km', 120);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestampsTz();
            $table->unique(['game_id', 'code']);
        });

        Schema::create('game_fields', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('game_id')->constrained('games')->cascadeOnDelete();
            $table->string('key', 64);
            $table->string('label_en', 120);
            $table->string('label_km', 120);
            $table->string('placeholder_en', 160)->nullable();
            $table->string('placeholder_km', 160)->nullable();
            $table->string('field_type', 24)->default('text'); // text, number, select
            $table->boolean('is_required')->default(true);
            $table->string('validation_regex', 255)->nullable();
            $table->json('options')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestampsTz();
            $table->unique(['game_id', 'key']);
        });

        Schema::create('game_packages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('game_id')->constrained('games')->restrictOnDelete();
            $table->string('internal_sku', 96)->unique();
            $table->string('name_en', 160);
            $table->string('name_km', 160);
            $table->string('entitlement_description_en', 255);
            $table->string('entitlement_description_km', 255);
            $table->string('bonus_label', 80)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->bigInteger('base_cost_minor');
            $table->bigInteger('selling_price_minor');
            $table->bigInteger('min_profit_minor')->default(0);
            $table->decimal('markup_percent', 6, 2)->default(8.00);
            $table->string('fulfillment_type', 32)->default('direct_topup');
            $table->boolean('is_promotion_eligible')->default(true);
            $table->boolean('is_available')->default(true)->index();
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('version')->default(1);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestampsTz();
            $table->index(['game_id', 'is_available', 'sort_order']);
        });

        Schema::create('package_regions', function (Blueprint $table) {
            $table->foreignUuid('game_package_id')->constrained('game_packages')->cascadeOnDelete();
            $table->foreignUuid('game_region_id')->constrained('game_regions')->cascadeOnDelete();
            $table->bigInteger('override_selling_price_minor')->nullable();
            $table->boolean('is_available')->default(true);
            $table->primary(['game_package_id', 'game_region_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_regions');
        Schema::dropIfExists('game_packages');
        Schema::dropIfExists('game_fields');
        Schema::dropIfExists('game_regions');
        Schema::dropIfExists('game_assets');
        Schema::dropIfExists('game_localizations');
        Schema::dropIfExists('games');
        Schema::dropIfExists('categories');
    }
};
