<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Admin extends Authenticatable
{
    use Notifiable,HasApiTokens;

    protected $fillable = [
        'name', 'email', 'password',
    ];

    protected $hidden = [
        'password',
    ];
}
