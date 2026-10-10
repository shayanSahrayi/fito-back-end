<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One coach and one athlete share a single relationship row.
     */
    public function up(): void
    {
        Schema::create('coaching_relationships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coach_profile_id')->constrained()->restrictOnDelete();
            $table->foreignId('athlete_profile_id')->constrained()->restrictOnDelete();
            $table->string('status', 32);
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();
            $table->foreignId('ended_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('end_reason', 160)->nullable();
            $table->dateTime('created_at');
            $table->dateTime('updated_at');

            $table->unique(
                ['coach_profile_id', 'athlete_profile_id'],
                'coach_athlete_pair_unique'
            );
            $table->index(['coach_profile_id', 'status']);
            $table->index(['athlete_profile_id', 'status']);
        });

        Schema::create('coaching_relationship_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coaching_relationship_id')->constrained()->restrictOnDelete();
            $table->string('event_type', 32);
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('happened_at');
            $table->string('note', 160)->nullable();
        });

        Schema::create('athlete_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coach_profile_id')->constrained()->restrictOnDelete();
            $table->char('phone', 11);
            $table->string('status', 32)->default('pending');
            // Null after the invite leaves pending, so the same pair can be invited again.
            $table->string('pending_key', 32)->nullable()->unique();
            $table->dateTime('expires_at');
            $table->dateTime('accepted_at')->nullable();
            $table->dateTime('revoked_at')->nullable();
            $table->foreignId('accepted_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('coaching_relationship_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->dateTime('created_at');
            $table->dateTime('updated_at');

            $table->index(['phone', 'status']);
            $table->index(['coach_profile_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('athlete_invitations');
        Schema::dropIfExists('coaching_relationship_events');
        Schema::dropIfExists('coaching_relationships');
    }
};
