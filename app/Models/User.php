<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

// ✅ DÙNG TRAIT CỦA PASSPORT (KHÔNG dùng Sanctum)
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $fillable = ['name','email','password'];

    protected $hidden = ['password','remember_token'];
}
