<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CoachKnowledgeDocument extends Model
{
    protected $fillable = [
        'coach_profile_id',
        'title',
        'storage_path',
        'sha256',
        'status',
        'created_by_user_id',
    ];

    public function chunks(): HasMany
    {
        return $this->hasMany(CoachKnowledgeChunk::class)->orderBy('chunk_index');
    }

    public function coachProfile(): BelongsTo
    {
        return $this->belongsTo(CoachProfile::class);
    }
}
