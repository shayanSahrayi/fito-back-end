<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoachProfile extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'display_name',
        'status',
        'current_ai_setting_version_id',
        'ai_onboarding_completed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ai_onboarding_completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
