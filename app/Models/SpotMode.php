<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpotMode extends Model
{
    protected $fillable = ['user_id', 'status','activate_time' ,'closing_time', 'sale'];

    protected $casts = [
        'activate_time' => 'datetime:H:i:s',
        'closing_time' => 'datetime:H:i:s',
    ];
    public function user()
    {
        return $this->belongsTo(User::class);
    }

}
