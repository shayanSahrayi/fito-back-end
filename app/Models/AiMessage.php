<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiMessage extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'ai_conversation_id',
        'sequence_no',
        'role',
        'body',
        'tool_name',
    ];
}
