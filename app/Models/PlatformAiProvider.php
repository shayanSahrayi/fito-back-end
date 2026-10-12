<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformAiProvider extends Model
{
    protected $fillable = [
        'provider',
        'model_name',
        'base_url',
        'api_key_encrypted',
        'updated_by_user_id',
    ];
}
