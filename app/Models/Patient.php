<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Patient extends Model
{
    protected $fillable = [
        'name',
        'email',
        'phone',
        'zip_code',
        'state',
        'city',
        'neighborhood',
        'street',
        'number',
        'complement',
        'date_of_birth',
        'age',
        'diseases',
        'telegram_chat_id',
    ];
}
