<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AmoCrmToken extends Model
{
    protected $table = 'amocrm_tokens';

    protected $fillable = [
        'access_token',
        'refresh_token',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];
}
