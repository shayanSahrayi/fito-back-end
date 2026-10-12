<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoachAiSettingVersion extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'coach_profile_id',
        'version_number',
        'methodology',
        'preferences',
        'constraints',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
        ];
    }

    public function coachProfile(): BelongsTo
    {
        return $this->belongsTo(CoachProfile::class);
    }
}
