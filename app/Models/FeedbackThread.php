<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeedbackThread extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'coaching_relationship_id',
        'opened_by_user_id',
        'workout_session_id',
        'workout_day_exercise_id',
        'exercise_id',
        'status',
    ];

    public function relationship(): BelongsTo
    {
        return $this->belongsTo(CoachingRelationship::class, 'coaching_relationship_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(FeedbackMessage::class)->orderBy('id');
    }
}
