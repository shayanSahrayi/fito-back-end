<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'name',
        'description',
        'price_irr',
        'billing_interval_unit',
        'billing_interval_count',
        'athlete_capacity',
        'ai_request_quota',
        'is_sellable',
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
            'is_sellable' => 'boolean',
        ];
    }

    public function features(): HasMany
    {
        return $this->hasMany(PlanFeature::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
