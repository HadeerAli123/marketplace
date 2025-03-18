<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = ['first_name', 'last_name', 'username', 'role', 'phone', 'image','email', 'email_verified_at', 'password', 'remember_token',];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = ['password', 'remember_token'];
    protected function casts(): array
{
    return [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];
}

    public function emails()
    {
        return $this->hasMany(UsersEmail::class);
    }

 
    public function addresses()
    {
        return $this->hasMany(UsersAddress::class);
    }
    public function products()
{
    return $this->hasMany(Product::class);
}

public function orders()
{
    return $this->hasMany(Order::class);
}

public function spotModes()
{
    return $this->hasMany(SpotMode::class);
}

public function deliveries()
{
    return $this->hasMany(Delivery::class, 'driver_id');
}
public function carts()
{
    return $this->hasMany(Cart::class);
}


public function getEmailForVerification()
    {
        return $this->emails()->where('type', 'primary')->first()->email ?? $this->emails()->first()->email;
    }

    /**
     * 
     */
    public function sendEmailVerificationNotification()
    {
        $this->notify(new \Illuminate\Auth\Notifications\VerifyEmail);
    }

    /**
     * 
     */
    public function getAuthPassword()
    {
        return $this->emails()->where('type', 'primary')->first()->password ?? $this->emails()->first()->password;
    }

    /**
     * 
     */
    public function getRememberToken()
    {
        return $this->emails()->where('type', 'primary')->first()->remember_token ?? $this->emails()->first()->remember_token;
    }

    /**
     * 
     */
    public function setRememberToken($value)
    {
        $email = $this->emails()->where('type', 'primary')->first() ?? $this->emails()->first();
        if ($email) {
            $email->update(['remember_token' => $value]);
        }
    }

    /**
     * 
     */
    public function getRememberTokenName()
    {
        return 'remember_token';
    }
}
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    

