<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Laravel\Passport\HasApiTokens;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;


class Documents extends Authenticatable
{
    //
    use HasApiTokens, Notifiable;

    protected $fillable = [
        'name', 'user_id', 'path', 'id_name'
    ];

    protected $hidden = [

    ];
}
