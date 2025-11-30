<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserSpecialization extends Model
{
    protected $table = 'user_specializations';
    protected $fillable = [
        'user_id',
        'specialization_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function specialization()
    {
        return $this->belongsTo(Specialization::class);
    }
}
