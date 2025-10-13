<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'avatar', 'last_login'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login' => 'datetime',
    ];

    protected $hidden = ['password', 'remember_token'];

    public function flashcardStats()
    {
        return $this->hasOne(UserFlashcardStat::class, 'user_id');
    }
}
