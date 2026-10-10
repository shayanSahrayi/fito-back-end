<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Body metrics, observations, and photos.
     * Missing measurements are absent rows, not zeros.
     */
    public function up(): void
    {
        Schema::create('body_metric_definitions', function (Blueprint $table) {
            $table->string('code', 64)->primary();
            $table->string('name_fa', 120);
            $table->string('unit_code', 32);
            $table->boolean('is_derived');
        });

        DB::table('body_metric_definitions')->insert([
            ['code' => 'weight_g', 'name_fa' => 'وزن', 'unit_code' => 'g', 'is_derived' => false],
            ['code' => 'height_mm', 'name_fa' => 'قد', 'unit_code' => 'mm', 'is_derived' => false],
            ['code' => 'body_fat_bp', 'name_fa' => 'درصد چربی', 'unit_code' => 'bp', 'is_derived' => false],
            ['code' => 'muscle_mass_g', 'name_fa' => 'توده عضلانی', 'unit_code' => 'g', 'is_derived' => false],
            ['code' => 'body_water_bp', 'name_fa' => 'آب بدن', 'unit_code' => 'bp', 'is_derived' => false],
            ['code' => 'visceral_fat_score', 'name_fa' => 'چربی احشایی', 'unit_code' => 'score', 'is_derived' => false],
            ['code' => 'bmi_centi', 'name_fa' => 'شاخص توده بدنی', 'unit_code' => 'bmi', 'is_derived' => true],
            ['code' => 'neck_mm', 'name_fa' => 'دور گردن', 'unit_code' => 'mm', 'is_derived' => false],
            ['code' => 'chest_mm', 'name_fa' => 'دور سینه', 'unit_code' => 'mm', 'is_derived' => false],
            ['code' => 'waist_mm', 'name_fa' => 'دور کمر', 'unit_code' => 'mm', 'is_derived' => false],
            ['code' => 'hip_mm', 'name_fa' => 'دور باسن', 'unit_code' => 'mm', 'is_derived' => false],
            ['code' => 'left_arm_mm', 'name_fa' => 'دور بازوی چپ', 'unit_code' => 'mm', 'is_derived' => false],
            ['code' => 'right_arm_mm', 'name_fa' => 'دور بازوی راست', 'unit_code' => 'mm', 'is_derived' => false],
            ['code' => 'left_thigh_mm', 'name_fa' => 'دور ران چپ', 'unit_code' => 'mm', 'is_derived' => false],
            ['code' => 'right_thigh_mm', 'name_fa' => 'دور ران راست', 'unit_code' => 'mm', 'is_derived' => false],
        ]);

        Schema::create('body_observations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('athlete_profile_id')->constrained()->restrictOnDelete();
            $table->foreignId('coaching_relationship_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('recorded_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('source', 16);
            $table->string('visibility', 32);
            $table->dateTime('measured_at');
            $table->string('note', 500)->nullable();
            $table->dateTime('created_at');

            $table->index(['athlete_profile_id', 'measured_at']);
            $table->index(['coaching_relationship_id', 'measured_at']);
        });

        Schema::create('body_observation_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('body_observation_id')->constrained()->restrictOnDelete();
            $table->string('metric_code', 64);
            $table->unsignedInteger('value_int');
            $table->string('origin', 16);
            $table->string('formula_code', 32)->nullable();

            $table->foreign('metric_code')->references('code')->on('body_metric_definitions')->restrictOnDelete();
            $table->unique(
                ['body_observation_id', 'metric_code', 'origin'],
                'observation_metric_origin_unique'
            );
        });

        Schema::create('body_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('athlete_profile_id')->constrained()->restrictOnDelete();
            $table->foreignId('coaching_relationship_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('uploaded_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('storage_path', 255);
            $table->dateTime('taken_at');
            $table->dateTime('sent_to_coach_at')->nullable();
            $table->dateTime('created_at');

            $table->index(['athlete_profile_id', 'taken_at']);
            $table->index(['coaching_relationship_id', 'taken_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('body_photos');
        Schema::dropIfExists('body_observation_values');
        Schema::dropIfExists('body_observations');
        Schema::dropIfExists('body_metric_definitions');
    }
};
