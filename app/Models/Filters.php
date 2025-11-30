<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Filters extends Model
{
    protected $fillable = [
        'type',
        'value'
    ];
}
