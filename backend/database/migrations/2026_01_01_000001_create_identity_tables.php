<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug', 64)->unique();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestampsTz();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug', 96)->unique();
            $table->string('name', 120);
            $table->string('module', 64)->index();
            $table->timestampsTz();
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->foreignUuid('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignUuid('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });

        Schema::create('user_roles', function (Blueprint $table) {
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('role_id')->constrained('roles')->cascadeOnDelete();
            $table->timestampTz('assigned_at')->useCurrent();
            $table->primary(['user_id', 'role_id']);
        });

        Schema::create('customer_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('telegram_chat_id_encrypted')->nullable();
            $table->boolean('notify_email')->default(true);
            $table->boolean('notify_in_app')->default(true);
            $table->boolean('notify_telegram')->default(false);
            $table->unsignedInteger('risk_score')->default(0);
            $table->boolean('is_flagged')->default(false);
            $table->json('saved_player_accounts')->nullable();
            $table->timestampsTz();
        });

        Schema::create('admin_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('token_id', 64)->nullable()->index();
            $table->string('ip_address', 45);
            $table->text('user_agent')->nullable();
            $table->boolean('mfa_verified')->default(false);
            $table->timestampTz('step_up_verified_at')->nullable();
            $table->timestampTz('last_active_at')->useCurrent();
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampsTz();
        });

        if (! Schema::hasTable('personal_access_tokens')) {
            Schema::create('personal_access_tokens', function (Blueprint $table) {
                $table->id();
                $table->uuidMorphs('tokenable');
                $table->string('name');
                $table->string('token', 64)->unique();
                $table->text('abilities')->nullable();
                $table->timestampTz('last_used_at')->nullable();
                $table->timestampTz('expires_at')->nullable()->index();
                $table->timestampsTz();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('admin_sessions');
        Schema::dropIfExists('customer_profiles');
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
