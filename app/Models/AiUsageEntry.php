<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiUsageEntry extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'access_period_id',
        'coach_profile_id',
        'actor_user_id',
        'athlete_profile_id',
        'coaching_relationship_id',
        'ai_generation_id',
        'kind',
        'units',
        'provider_prompt_tokens',
        'provider_completion_tokens',
    ];
}
