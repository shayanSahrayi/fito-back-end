<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkoutDayExercise extends Model
{
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'workout_day_id',
        'exercise_id',
        'position',
        'tracking_type',
        'prescribed_sets',
        'reps_min',
        'reps_max',
        'target_weight_g',
        'rest_seconds',
        'duration_seconds',
        'distance_m',
        'coach_note',
    ];

    public function day(): BelongsTo
    {
        return $this->belongsTo(WorkoutDay::class, 'workout_day_id');
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }
}
