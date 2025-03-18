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


    protected $table = 'spot_mode';

public  static function  isActive(){
    $now=now();
    $currentTime=$now->toTimeString();
    $currentDate=$now->toDateString();
     return self::where('status','activat') //بتعمل استعلام على جدول spot_mode بشروط:
     ->whereDate('created_at', $currentDate)
     ->where('activate_time', '<=', $currentTime)
     ->where('closing_time', '>=', $currentTime)->exists();
}
    public function user()
    {
        return $this->belongsTo(User::class);
    }

}
