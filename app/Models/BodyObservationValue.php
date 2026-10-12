<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BodyObservationValue extends Model
{
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'body_observation_id',
        'metric_code',
        'value_int',
        'origin',
        'formula_code',
    ];

    public function observation(): BelongsTo
    {
        return $this->belongsTo(BodyObservation::class, 'body_observation_id');
    }
}
