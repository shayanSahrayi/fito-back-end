<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkoutDay extends Model
{
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'workout_program_version_id',
        'position',
        'title',
        'weekday',
    ];

    public function version(): BelongsTo
    {
        return $this->belongsTo(WorkoutProgramVersion::class, 'workout_program_version_id');
    }

    public function exercises(): HasMany
    {
        return $this->hasMany(WorkoutDayExercise::class)->orderBy('position')->orderBy('id');
    }
}
