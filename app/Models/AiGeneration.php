<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiGeneration extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'coach_profile_id',
        'coaching_relationship_id',
        'athlete_profile_id',
        'ai_conversation_id',
        'coach_ai_setting_version_id',
        'kind',
        'status',
        'proposal',
        'model_name',
        'reviewed_by_user_id',
        'reviewed_at',
        'workout_program_version_id',
        'meal_plan_version_id',
    ];

    protected function casts(): array
    {
        return [
            'proposal' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }
}
