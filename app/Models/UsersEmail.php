<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsersEmail extends Model
{
    
    protected $fillable = ['user_id','email' ];
    
 
        public function user()
        {
            return $this->belongsTo(User::class);
        }
    }

