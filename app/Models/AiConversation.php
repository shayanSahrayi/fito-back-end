<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiConversation extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'coach_profile_id',
        'coaching_relationship_id',
        'athlete_profile_id',
        'actor_user_id',
        'audience',
        'coach_ai_setting_version_id',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(AiMessage::class)->orderBy('sequence_no');
    }
}
