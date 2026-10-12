<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MealDay extends Model
{
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'meal_plan_version_id',
        'position',
        'title',
    ];

    public function version(): BelongsTo
    {
        return $this->belongsTo(MealPlanVersion::class, 'meal_plan_version_id');
    }

    public function meals(): HasMany
    {
        return $this->hasMany(Meal::class)->orderBy('position')->orderBy('id');
    }
}
