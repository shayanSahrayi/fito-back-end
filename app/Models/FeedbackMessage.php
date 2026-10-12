<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedbackMessage extends Model
{
    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'feedback_thread_id',
        'author_user_id',
        'visibility',
        'body',
        'pain_level',
    ];

    public function thread(): BelongsTo
    {
        return $this->belongsTo(FeedbackThread::class, 'feedback_thread_id');
    }
}
