<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAttempt extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'order_id',
        'gateway',
        'amount_irr',
        'status',
        'idempotency_key',
        'gateway_request_id',
        'gateway_reference',
        'failure_code',
        'gateway_payload',
        'finished_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_irr' => 'integer',
            'gateway_payload' => 'array',
            'finished_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function payment(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Payment::class);
    }
}
