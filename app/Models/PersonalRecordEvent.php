<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonalRecordEvent extends Model
{
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'athlete_profile_id',
        'exercise_id',
        'coaching_relationship_id',
        'metric',
        'value_int',
        'workout_session_set_id',
        'formula_code',
        'recorded_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
        ];
    }

    public function set(): BelongsTo
    {
        return $this->belongsTo(WorkoutSessionSet::class, 'workout_session_set_id');
    }
}
