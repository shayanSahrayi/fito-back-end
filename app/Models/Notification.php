<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'notifications';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'type',
        'title',
        'body',
        'subject_type',
        'subject_id',
        'read_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
