<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Relationship feedback and in-app notifications.
     * coach_only messages stay on the same table and are filtered in SQL.
     */
    public function up(): void
    {
        Schema::create('feedback_threads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coaching_relationship_id')->constrained()->restrictOnDelete();
            $table->foreignId('opened_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('workout_session_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('workout_day_exercise_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('exercise_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('status', 16);
            $table->dateTime('created_at');
            $table->dateTime('updated_at');

            $table->index(['coaching_relationship_id', 'status']);
        });

        Schema::create('feedback_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feedback_thread_id')->constrained()->restrictOnDelete();
            $table->foreignId('author_user_id')->constrained('users')->restrictOnDelete();
            $table->string('visibility', 16);
            $table->text('body');
            $table->unsignedTinyInteger('pain_level')->nullable();
            $table->dateTime('created_at');

            $table->index(['feedback_thread_id', 'visibility', 'id']);
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('type', 64);
            $table->string('title', 160);
            $table->string('body', 500);
            $table->string('subject_type', 64)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->dateTime('read_at')->nullable();
            $table->dateTime('created_at');

            $table->index(['user_id', 'read_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_messages');
        Schema::dropIfExists('feedback_threads');
        Schema::dropIfExists('notifications');
    }
};
