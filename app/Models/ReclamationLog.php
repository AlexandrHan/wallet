<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReclamationLog extends Model
{
    protected $fillable = [
        'reclamation_id',
        'user_id',
        'step_key',
        'action',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    
    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

}
