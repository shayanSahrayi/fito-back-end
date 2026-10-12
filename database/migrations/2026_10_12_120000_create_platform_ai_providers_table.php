<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One platform connection for the shared assistant model.
     * The API key is stored encrypted and is never returned to coaches.
     */
    public function up(): void
    {
        Schema::create('platform_ai_providers', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32);
            $table->string('model_name', 120);
            $table->string('base_url', 255)->nullable();
            $table->text('api_key_encrypted');
            $table->foreignId('updated_by_user_id')->constrained('users')->restrictOnDelete();
            $table->dateTime('created_at');
            $table->dateTime('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_ai_providers');
    }
};
