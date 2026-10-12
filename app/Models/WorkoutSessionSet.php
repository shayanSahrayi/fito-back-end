<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkoutSessionSet extends Model
{
    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'workout_session_id',
        'sequence_no',
        'workout_day_exercise_id',
        'exercise_id',
        'set_index',
        'reps',
        'weight_g',
        'duration_seconds',
        'distance_m',
        'is_completed',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_completed' => 'boolean',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(WorkoutSession::class, 'workout_session_id');
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }

    public function records(): HasMany
    {
        return $this->hasMany(PersonalRecordEvent::class, 'workout_session_set_id')->orderBy('id');
    }
}
