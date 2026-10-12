<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Exercise extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'coach_profile_id',
        'name',
        'instructions',
        'goal',
        'tracking_type',
        'status',
        'created_by_user_id',
    ];

    public function exerciseMuscles(): HasMany
    {
        return $this->hasMany(ExerciseMuscle::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(ExerciseMedia::class)->orderBy('sort_order')->orderBy('id');
    }
}
