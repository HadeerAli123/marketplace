<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsersAddress extends Model
{
    protected $fillable = [
        'user_id',
        'country',
        'state',
        'zip_code',
        'city',
        'address',
        'type',
        'company_name',
    ];
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
