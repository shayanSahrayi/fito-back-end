<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkoutAssignment extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'coaching_relationship_id',
        'athlete_profile_id',
        'workout_program_version_id',
        'assigned_by_user_id',
        'starts_on',
        'ends_on',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    public function relationship(): BelongsTo
    {
        return $this->belongsTo(CoachingRelationship::class, 'coaching_relationship_id');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(WorkoutProgramVersion::class, 'workout_program_version_id');
    }
}
