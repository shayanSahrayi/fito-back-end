<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkoutProgram extends Model
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
        return $this->hasMany(WorkoutProgramVersion::class);
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(WorkoutProgramVersion::class, 'current_version_id');
    }
}
