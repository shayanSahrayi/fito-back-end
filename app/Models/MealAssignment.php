<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MealAssignment extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'coaching_relationship_id',
        'athlete_profile_id',
        'meal_plan_version_id',
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

    public function version(): BelongsTo
    {
        return $this->belongsTo(MealPlanVersion::class, 'meal_plan_version_id');
    }
}
