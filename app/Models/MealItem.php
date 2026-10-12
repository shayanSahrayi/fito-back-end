<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MealItem extends Model
{
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'meal_id',
        'position',
        'food_id',
        'food_nutrient_revision_id',
        'quantity_g',
        'quantity_unit_milli',
    ];

    public function meal(): BelongsTo
    {
        return $this->belongsTo(Meal::class);
    }

    public function food(): BelongsTo
    {
        return $this->belongsTo(Food::class);
    }

    public function revision(): BelongsTo
    {
        return $this->belongsTo(FoodNutrientRevision::class, 'food_nutrient_revision_id');
    }
}
