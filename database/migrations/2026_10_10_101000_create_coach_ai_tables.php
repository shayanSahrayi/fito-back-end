<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-coach AI memory on the shared model.
     * coach_profile_id remains the data boundary.
     */
    public function up(): void
    {
        Schema::create('coach_ai_workspaces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coach_profile_id')->unique()->constrained()->restrictOnDelete();
            $table->dateTime('created_at');
        });

        Schema::create('coach_ai_setting_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coach_profile_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('version_number');
            $table->text('methodology');
            $table->text('preferences');
            $table->text('constraints');
            $table->dateTime('created_at');

            $table->unique(
                ['coach_profile_id', 'version_number'],
                'coach_ai_setting_number_unique'
            );
        });

        Schema::create('coach_knowledge_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coach_profile_id')->constrained()->restrictOnDelete();
            $table->string('title', 160);
            $table->string('storage_path', 255);
            $table->char('sha256', 64);
            $table->string('status', 32);
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->dateTime('created_at');
            $table->dateTime('updated_at');

            $table->unique(['coach_profile_id', 'sha256']);
            $table->index(['coach_profile_id', 'status']);
        });

        Schema::create('coach_knowledge_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coach_knowledge_document_id')->constrained()->restrictOnDelete();
            $table->foreignId('coach_profile_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('chunk_index');
            $table->text('body');

            $table->unique(
                ['coach_knowledge_document_id', 'chunk_index'],
                'knowledge_chunk_index_unique'
            );
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            Schema::table('coach_knowledge_chunks', function (Blueprint $table) {
                $table->fullText('body');
            });
        }

        Schema::create('ai_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coach_profile_id')->constrained()->restrictOnDelete();
            $table->foreignId('coaching_relationship_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('athlete_profile_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->string('audience', 16);
            $table->foreignId('coach_ai_setting_version_id')->constrained()->restrictOnDelete();
            $table->dateTime('created_at');

            $table->index(['coach_profile_id', 'id']);
        });

        Schema::create('ai_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_conversation_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('sequence_no');
            $table->string('role', 16);
            $table->text('body');
            $table->string('tool_name', 64)->nullable();
            $table->dateTime('created_at');

            $table->unique(['ai_conversation_id', 'sequence_no']);
        });

        Schema::create('ai_generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coach_profile_id')->constrained()->restrictOnDelete();
            $table->foreignId('coaching_relationship_id')->constrained()->restrictOnDelete();
            $table->foreignId('athlete_profile_id')->constrained()->restrictOnDelete();
            $table->foreignId('ai_conversation_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('coach_ai_setting_version_id')->constrained()->restrictOnDelete();
            $table->string('kind', 32);
            $table->string('status', 32);
            $table->json('proposal');
            $table->string('model_name', 120);
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->foreignId('workout_program_version_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('meal_plan_version_id')->nullable()->constrained()->restrictOnDelete();
            $table->dateTime('created_at');

            $table->index(['coach_profile_id', 'status', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_generations');
        Schema::dropIfExists('ai_messages');
        Schema::dropIfExists('ai_conversations');
        Schema::dropIfExists('coach_knowledge_chunks');
        Schema::dropIfExists('coach_knowledge_documents');
        Schema::dropIfExists('coach_ai_setting_versions');
        Schema::dropIfExists('coach_ai_workspaces');
    }
};
