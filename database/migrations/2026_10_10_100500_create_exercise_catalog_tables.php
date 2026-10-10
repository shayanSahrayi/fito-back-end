<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Exercise catalog. A null coach_profile_id is the platform bank.
     */
    public function up(): void
    {
        Schema::create('muscles', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name_fa', 120);
            $table->string('name_en', 120)->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('muscles')->restrictOnDelete();
        });

        Schema::create('body_model_areas', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name_fa', 120);
            $table->string('name_en', 120)->nullable();
        });

        Schema::create('muscle_body_area', function (Blueprint $table) {
            $table->foreignId('muscle_id')->constrained()->restrictOnDelete();
            $table->foreignId('body_model_area_id')->constrained()->restrictOnDelete();

            $table->primary(['muscle_id', 'body_model_area_id']);
        });

        Schema::create('exercises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coach_profile_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('name', 160);
            $table->text('instructions')->nullable();
            $table->text('goal')->nullable();
            $table->string('tracking_type', 32);
            $table->string('status', 32)->default('active');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('created_at');
            $table->dateTime('updated_at');

            $table->index(['coach_profile_id', 'status']);
        });

        Schema::create('exercise_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exercise_id')->constrained()->restrictOnDelete();
            $table->string('media_type', 16);
            $table->string('path', 255);
            $table->unsignedSmallInteger('sort_order');
            $table->dateTime('created_at');

            $table->index(['exercise_id', 'sort_order']);
        });

        Schema::create('exercise_muscles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exercise_id')->constrained()->restrictOnDelete();
            $table->foreignId('muscle_id')->constrained()->restrictOnDelete();
            $table->string('involvement', 16);

            $table->unique(['exercise_id', 'muscle_id']);
        });

        Schema::create('exercise_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coach_profile_id')->constrained()->restrictOnDelete();
            $table->string('proposed_name', 160);
            $table->text('instructions')->nullable();
            $table->text('goal')->nullable();
            $table->string('tracking_type', 32);
            $table->string('status', 32);
            $table->string('review_note', 500)->nullable();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('exercise_id')->nullable()->constrained()->restrictOnDelete();
            $table->dateTime('created_at');
            $table->dateTime('updated_at');

            $table->index(['coach_profile_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exercise_requests');
        Schema::dropIfExists('exercise_muscles');
        Schema::dropIfExists('exercise_media');
        Schema::dropIfExists('exercises');
        Schema::dropIfExists('muscle_body_area');
        Schema::dropIfExists('body_model_areas');
        Schema::dropIfExists('muscles');
    }
};
