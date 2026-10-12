<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Meal extends Model
{
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'meal_day_id',
        'position',
        'meal_type',
        'title',
    ];

    public function day(): BelongsTo
    {
        return $this->belongsTo(MealDay::class, 'meal_day_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(MealItem::class)->orderBy('position')->orderBy('id');
    }
}
