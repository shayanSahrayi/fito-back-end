<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkoutProgramVersion extends Model
{
    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'workout_program_id',
        'version_number',
        'state',
        'note',
        'published_at',
        'created_by_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(WorkoutProgram::class, 'workout_program_id');
    }

    public function days(): HasMany
    {
        return $this->hasMany(WorkoutDay::class)->orderBy('position')->orderBy('id');
    }
}
