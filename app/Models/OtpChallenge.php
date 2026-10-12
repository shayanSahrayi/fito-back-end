<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OtpChallenge extends Model
{
    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'phone',
        'purpose',
        'code_hash',
        'expires_at',
        'attempt_count',
        'consumed_at',
        'signup_draft_id',
        'athlete_invitation_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function signupDraft(): BelongsTo
    {
        return $this->belongsTo(SignupDraft::class);
    }
}
