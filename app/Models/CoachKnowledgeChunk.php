<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoachKnowledgeChunk extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'coach_knowledge_document_id',
        'coach_profile_id',
        'chunk_index',
        'body',
    ];
}
