<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkoutSession extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'athlete_profile_id',
        'coaching_relationship_id',
        'workout_assignment_id',
        'workout_day_id',
        'performed_on',
        'started_at',
        'completed_at',
        'status',
        'source',
        'perceived_effort',
        'athlete_note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'performed_on' => 'date',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function sets(): HasMany
    {
        return $this->hasMany(WorkoutSessionSet::class)->orderBy('sequence_no')->orderBy('id');
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(WorkoutAssignment::class, 'workout_assignment_id');
    }
}
