<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BodyPhoto extends Model
{
    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'athlete_profile_id',
        'coaching_relationship_id',
        'uploaded_by_user_id',
        'storage_path',
        'taken_at',
        'sent_to_coach_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'taken_at' => 'datetime',
            'sent_to_coach_at' => 'datetime',
        ];
    }

    public function relationship(): BelongsTo
    {
        return $this->belongsTo(CoachingRelationship::class, 'coaching_relationship_id');
    }
}
