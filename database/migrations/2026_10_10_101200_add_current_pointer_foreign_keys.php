<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Closes the four cycles that cannot be declared while both tables are created.
     */
    public function up(): void
    {
        Schema::table('coach_profiles', function (Blueprint $table) {
            $table->foreign('current_ai_setting_version_id')
                ->references('id')
                ->on('coach_ai_setting_versions')
                ->restrictOnDelete();
        });

        Schema::table('workout_programs', function (Blueprint $table) {
            $table->foreign('current_version_id')
                ->references('id')
                ->on('workout_program_versions')
                ->restrictOnDelete();
        });

        Schema::table('foods', function (Blueprint $table) {
            $table->foreign('current_revision_id')
                ->references('id')
                ->on('food_nutrient_revisions')
                ->restrictOnDelete();
        });

        Schema::table('meal_plans', function (Blueprint $table) {
            $table->foreign('current_version_id')
                ->references('id')
                ->on('meal_plan_versions')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('meal_plans', function (Blueprint $table) {
            $table->dropForeign(['current_version_id']);
        });

        Schema::table('foods', function (Blueprint $table) {
            $table->dropForeign(['current_revision_id']);
        });

        Schema::table('workout_programs', function (Blueprint $table) {
            $table->dropForeign(['current_version_id']);
        });

        Schema::table('coach_profiles', function (Blueprint $table) {
            $table->dropForeign(['current_ai_setting_version_id']);
        });
    }
};
