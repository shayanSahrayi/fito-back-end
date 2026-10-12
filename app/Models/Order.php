<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'coach_profile_id',
        'plan_id',
        'status',
        'price_irr',
        'billing_interval_unit',
        'billing_interval_count',
        'athlete_capacity',
        'ai_request_quota',
        'open_checkout_coach_id',
        'expires_at',
        'paid_at',
        'cancelled_at',
        'created_by_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_irr' => 'integer',
            'billing_interval_count' => 'integer',
            'athlete_capacity' => 'integer',
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function coachProfile(): BelongsTo
    {
        return $this->belongsTo(CoachProfile::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function features(): HasMany
    {
        return $this->hasMany(OrderFeature::class);
    }

    public function paymentAttempts(): HasMany
    {
        return $this->hasMany(PaymentAttempt::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function accessPeriod(): HasOne
    {
        return $this->hasOne(AccessPeriod::class);
    }
}
