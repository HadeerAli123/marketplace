<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsersEmail extends Model
{
    
    protected $fillable = ['user_id', 'email', 'email_verified_at', 'password', 'remember_token', 'type'];
    
        protected $hidden = ['password', 'remember_token'];
        protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
    
        public function user()
        {
            return $this->belongsTo(User::class);
        }
    }
///// basics
