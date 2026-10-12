<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Food extends Model
{
    protected $table = 'foods';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'coach_profile_id',
        'name',
        'status',
        'current_revision_id',
    ];

    public function currentRevision(): BelongsTo
    {
        return $this->belongsTo(FoodNutrientRevision::class, 'current_revision_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(FoodNutrientRevision::class);
    }
}
