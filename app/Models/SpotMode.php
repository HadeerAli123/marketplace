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
    public static function isActive()
    {
        $now = now(); // يحتوي على التاريخ والوقت (مثل "2025-04-06 11:15:00")
        return self::where('status', 'active')
            ->where('activate_time', '<=', $now)
            ->where('closing_time', '>=', $now)
            ->exists();
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }

}
