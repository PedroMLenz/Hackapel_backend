<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Information extends Model
{
    protected $fillable = [
        'title',
        'content',
        'user_id',
        'filters',
        'patients_sended_count',
    ];
}
