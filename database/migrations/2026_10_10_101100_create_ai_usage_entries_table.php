<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Quota ledger. The matching counter lives on access_periods.ai_requests_used.
     */
    public function up(): void
    {
        Schema::create('ai_usage_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('access_period_id')->constrained()->restrictOnDelete();
            $table->foreignId('coach_profile_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('athlete_profile_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('coaching_relationship_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('ai_generation_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('kind', 32);
            $table->unsignedInteger('units')->default(1);
            $table->unsignedInteger('provider_prompt_tokens')->nullable();
            $table->unsignedInteger('provider_completion_tokens')->nullable();
            $table->dateTime('created_at');

            $table->index(['access_period_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_entries');
    }
};
