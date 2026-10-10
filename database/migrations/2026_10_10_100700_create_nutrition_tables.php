<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Foods and meal plans. Published versions and nutrient revisions stay immutable.
     * Current pointers are constrained in the pointer migration.
     */
    public function up(): void
    {
        Schema::create('foods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coach_profile_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('name', 160);
            $table->string('status', 32);
            $table->unsignedBigInteger('current_revision_id')->nullable()->index();
            $table->dateTime('created_at');
            $table->dateTime('updated_at');

            $table->index(['coach_profile_id', 'status']);
        });

        Schema::create('food_nutrient_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('food_id')->constrained('foods')->restrictOnDelete();
            $table->unsignedInteger('revision_number');
            $table->string('basis', 16);
            $table->string('unit_code', 16);
            $table->string('unit_label', 40);
            $table->unsignedInteger('grams_per_unit')->nullable();
            $table->unsignedInteger('energy_kcal');
            $table->unsignedInteger('protein_mg');
            $table->unsignedInteger('carbohydrate_mg');
            $table->unsignedInteger('fat_mg');
            $table->dateTime('created_at');

            $table->unique(['food_id', 'revision_number']);
        });

        Schema::create('meal_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coach_profile_id')->constrained()->restrictOnDelete();
            $table->string('title', 160);
            $table->string('status', 32);
            $table->unsignedBigInteger('current_version_id')->nullable()->index();
            $table->dateTime('created_at');
            $table->dateTime('updated_at');

            $table->index(['coach_profile_id', 'status']);
        });

        Schema::create('meal_plan_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meal_plan_id')->constrained('meal_plans')->restrictOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('state', 32);
            $table->dateTime('published_at')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->dateTime('created_at');

            $table->unique(['meal_plan_id', 'version_number']);
        });

        Schema::create('meal_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meal_plan_version_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('title', 120);

            $table->unique(['meal_plan_version_id', 'position']);
        });

        Schema::create('meals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meal_day_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('meal_type', 16);
            $table->string('title', 120)->nullable();

            $table->unique(['meal_day_id', 'position']);
        });

        Schema::create('meal_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meal_id')->constrained('meals')->restrictOnDelete();
            $table->unsignedSmallInteger('position');
            $table->foreignId('food_id')->constrained('foods')->restrictOnDelete();
            $table->foreignId('food_nutrient_revision_id')->constrained('food_nutrient_revisions')->restrictOnDelete();
            $table->unsignedInteger('quantity_g')->nullable();
            $table->unsignedInteger('quantity_unit_milli')->nullable();

            $table->unique(['meal_id', 'position']);
        });

        Schema::create('meal_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coaching_relationship_id')->constrained()->restrictOnDelete();
            $table->foreignId('athlete_profile_id')->constrained()->restrictOnDelete();
            $table->foreignId('meal_plan_version_id')->constrained()->restrictOnDelete();
            $table->foreignId('assigned_by_user_id')->constrained('users')->restrictOnDelete();
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->string('status', 32);
            $table->dateTime('created_at');
            $table->dateTime('updated_at');

            $table->index(['athlete_profile_id', 'status']);
            $table->index(['coaching_relationship_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meal_assignments');
        Schema::dropIfExists('meal_items');
        Schema::dropIfExists('meals');
        Schema::dropIfExists('meal_days');
        Schema::dropIfExists('meal_plan_versions');
        Schema::dropIfExists('meal_plans');
        Schema::dropIfExists('food_nutrient_revisions');
        Schema::dropIfExists('foods');
    }
};
