<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Identity tables from docs/product/02-database.md.
     * The existing users table stays unchanged until F01.
     */
    public function up(): void
    {
        Schema::create('platform_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('role', 32);
            $table->foreignId('granted_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('created_at');

            $table->unique(['user_id', 'role']);
        });

        Schema::create('coach_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->string('display_name', 120);
            $table->string('status', 32)->default('active');
            $table->unsignedBigInteger('current_ai_setting_version_id')->nullable()->index();
            $table->dateTime('ai_onboarding_completed_at')->nullable();
            $table->dateTime('created_at');
            $table->dateTime('updated_at');
        });

        Schema::create('athlete_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->string('display_name', 120);
            $table->string('status', 32)->default('active');
            $table->dateTime('created_at');
            $table->dateTime('updated_at');
        });

        Schema::create('signup_drafts', function (Blueprint $table) {
            $table->id();
            $table->char('phone', 11);
            $table->dateTime('phone_verified_at')->nullable();
            $table->char('national_code', 10)->nullable();
            $table->string('display_name', 120)->nullable();
            $table->string('panel', 32)->nullable();
            // Null once consumed, so several finished drafts can share a phone.
            $table->char('open_phone', 11)->nullable()->unique();
            $table->dateTime('expires_at');
            $table->dateTime('consumed_at')->nullable();
            $table->foreignId('created_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('created_at');
            $table->dateTime('updated_at');

            $table->index('phone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signup_drafts');
        Schema::dropIfExists('athlete_profiles');
        Schema::dropIfExists('coach_profiles');
        Schema::dropIfExists('platform_roles');
    }
};
