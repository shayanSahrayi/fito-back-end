<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Plans, orders, payments, and access periods.
     * Money is an integer number of rials.
     */
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('price_irr');
            $table->string('billing_interval_unit', 16);
            $table->unsignedSmallInteger('billing_interval_count');
            $table->unsignedInteger('athlete_capacity');
            $table->unsignedInteger('ai_request_quota')->nullable();
            $table->boolean('is_sellable')->default(false);
            $table->dateTime('created_at');
            $table->dateTime('updated_at');
        });

        Schema::create('plan_features', function (Blueprint $table) {
            $table->id();
            // Orders reference plans with RESTRICT, so this cascade runs only for a plan that was never ordered.
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->string('feature_key', 64);

            $table->unique(['plan_id', 'feature_key']);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coach_profile_id')->constrained()->restrictOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->string('status', 32)->default('pending');
            $table->unsignedBigInteger('price_irr');
            $table->string('billing_interval_unit', 16);
            $table->unsignedSmallInteger('billing_interval_count');
            $table->unsignedInteger('athlete_capacity');
            $table->unsignedInteger('ai_request_quota')->nullable();
            // Null after the checkout closes, so a coach can have another pending order later.
            $table->unsignedBigInteger('open_checkout_coach_id')->nullable()->unique();
            $table->dateTime('expires_at');
            $table->dateTime('paid_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->dateTime('created_at');
            $table->dateTime('updated_at');

            $table->index(['coach_profile_id', 'status']);
        });

        Schema::create('order_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->string('feature_key', 64);

            $table->unique(['order_id', 'feature_key']);
        });

        Schema::create('payment_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->string('gateway', 32);
            $table->unsignedBigInteger('amount_irr');
            $table->string('status', 32)->default('initiated');
            $table->char('idempotency_key', 64)->unique();
            $table->string('gateway_request_id', 191)->nullable()->unique();
            $table->string('gateway_reference', 191)->nullable()->unique();
            $table->string('failure_code', 64)->nullable();
            $table->json('gateway_payload')->nullable();
            $table->dateTime('finished_at')->nullable();
            $table->dateTime('created_at');
            $table->dateTime('updated_at');
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('payment_attempt_id')->unique()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount_irr');
            $table->string('gateway', 32);
            $table->string('gateway_reference', 191)->unique();
            $table->dateTime('confirmed_at');
            $table->dateTime('created_at');
        });

        Schema::create('access_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coach_profile_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->unique()->constrained()->restrictOnDelete();
            $table->string('status', 32);
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->unsignedInteger('athlete_capacity');
            $table->unsignedInteger('ai_request_quota')->nullable();
            $table->unsignedInteger('ai_requests_used')->default(0);
            // Null unless this is the coach's single active period.
            $table->unsignedBigInteger('active_coach_id')->nullable()->unique();
            $table->dateTime('created_at');
            $table->dateTime('updated_at');

            $table->index(['coach_profile_id', 'status', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_periods');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('payment_attempts');
        Schema::dropIfExists('order_features');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('plan_features');
        Schema::dropIfExists('plans');
    }
};
