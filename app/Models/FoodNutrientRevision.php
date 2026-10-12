<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FoodNutrientRevision extends Model
{
    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'food_id',
        'revision_number',
        'basis',
        'unit_code',
        'unit_label',
        'grams_per_unit',
        'energy_kcal',
        'protein_mg',
        'carbohydrate_mg',
        'fat_mg',
    ];

    public function food(): BelongsTo
    {
        return $this->belongsTo(Food::class);
    }
}
