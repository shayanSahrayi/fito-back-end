<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BodyObservation extends Model
{
    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'athlete_profile_id',
        'coaching_relationship_id',
        'recorded_by_user_id',
        'source',
        'visibility',
        'measured_at',
        'note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'measured_at' => 'datetime',
        ];
    }

    public function athleteProfile(): BelongsTo
    {
        return $this->belongsTo(AthleteProfile::class);
    }

    public function relationship(): BelongsTo
    {
        return $this->belongsTo(CoachingRelationship::class, 'coaching_relationship_id');
    }

    public function values(): HasMany
    {
        return $this->hasMany(BodyObservationValue::class);
    }
}
