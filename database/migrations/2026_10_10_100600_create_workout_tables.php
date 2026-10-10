<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Prescribed workouts, performed sessions, and record events.
     * current_version_id is constrained in the pointer migration.
     */
    public function up(): void
    {
        Schema::create('workout_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coach_profile_id')->constrained()->restrictOnDelete();
            $table->string('title', 160);
            $table->string('status', 32);
            $table->unsignedBigInteger('current_version_id')->nullable()->index();
            $table->dateTime('created_at');
            $table->dateTime('updated_at');

            $table->index(['coach_profile_id', 'status']);
        });

        Schema::create('workout_program_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workout_program_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('state', 32);
            $table->text('note')->nullable();
            $table->dateTime('published_at')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->dateTime('created_at');

            $table->unique(
                ['workout_program_id', 'version_number'],
                'program_version_number_unique'
            );
        });

        Schema::create('workout_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workout_program_version_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('title', 120);
            $table->unsignedTinyInteger('weekday')->nullable();

            $table->unique(['workout_program_version_id', 'position']);
        });

        Schema::create('workout_day_exercises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workout_day_id')->constrained()->restrictOnDelete();
            $table->foreignId('exercise_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('tracking_type', 32);
            $table->unsignedSmallInteger('prescribed_sets')->nullable();
            $table->unsignedSmallInteger('reps_min')->nullable();
            $table->unsignedSmallInteger('reps_max')->nullable();
            $table->unsignedInteger('target_weight_g')->nullable();
            $table->unsignedSmallInteger('rest_seconds')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->unsignedInteger('distance_m')->nullable();
            $table->string('coach_note', 500)->nullable();

            $table->unique(['workout_day_id', 'position']);
        });

        Schema::create('workout_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coaching_relationship_id')->constrained()->restrictOnDelete();
            $table->foreignId('athlete_profile_id')->constrained()->restrictOnDelete();
            $table->foreignId('workout_program_version_id')->constrained()->restrictOnDelete();
            $table->foreignId('assigned_by_user_id')->constrained('users')->restrictOnDelete();
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->string('status', 32);
            $table->dateTime('created_at');
            $table->dateTime('updated_at');

            $table->index(['athlete_profile_id', 'status']);
            $table->index(['coaching_relationship_id', 'status']);
        });

        Schema::create('workout_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('athlete_profile_id')->constrained()->restrictOnDelete();
            $table->foreignId('coaching_relationship_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('workout_assignment_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('workout_day_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('performed_on');
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->string('status', 32);
            $table->string('source', 32);
            $table->unsignedTinyInteger('perceived_effort')->nullable();
            $table->string('athlete_note', 500)->nullable();
            $table->dateTime('created_at');
            $table->dateTime('updated_at');

            $table->index(['athlete_profile_id', 'performed_on']);
            $table->index(['coaching_relationship_id', 'performed_on']);
        });

        Schema::create('workout_session_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workout_session_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('sequence_no');
            $table->foreignId('workout_day_exercise_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('exercise_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('set_index');
            $table->unsignedSmallInteger('reps')->nullable();
            $table->unsignedInteger('weight_g')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->unsignedInteger('distance_m')->nullable();
            $table->boolean('is_completed')->default(true);
            $table->dateTime('created_at');

            $table->unique(['workout_session_id', 'sequence_no']);
        });

        Schema::create('personal_record_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('athlete_profile_id')->constrained()->restrictOnDelete();
            $table->foreignId('exercise_id')->constrained()->restrictOnDelete();
            $table->foreignId('coaching_relationship_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('metric', 32);
            $table->unsignedInteger('value_int');
            $table->foreignId('workout_session_set_id')->constrained()->restrictOnDelete();
            $table->string('formula_code', 32)->nullable();
            $table->dateTime('recorded_at');

            $table->index(
                ['athlete_profile_id', 'exercise_id', 'metric', 'recorded_at'],
                'athlete_exercise_metric_time_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_record_events');
        Schema::dropIfExists('workout_session_sets');
        Schema::dropIfExists('workout_sessions');
        Schema::dropIfExists('workout_assignments');
        Schema::dropIfExists('workout_day_exercises');
        Schema::dropIfExists('workout_days');
        Schema::dropIfExists('workout_program_versions');
        Schema::dropIfExists('workout_programs');
    }
};
