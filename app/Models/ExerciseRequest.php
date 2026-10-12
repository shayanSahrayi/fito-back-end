<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExerciseRequest extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'coach_profile_id',
        'proposed_name',
        'instructions',
        'goal',
        'tracking_type',
        'status',
        'review_note',
        'reviewed_by_user_id',
        'exercise_id',
    ];

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }
}
