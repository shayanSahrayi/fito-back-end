<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MealPlan extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'coach_profile_id',
        'title',
        'status',
        'current_version_id',
    ];

    public function versions(): HasMany
    {
        return $this->hasMany(MealPlanVersion::class);
    }
}
