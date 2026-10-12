<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BodyModelArea extends Model
{
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'name_fa',
        'name_en',
    ];
}
