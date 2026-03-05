<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AmoComplectationProject extends Model
{
    protected $table = 'amo_complectation_projects';

    protected $fillable = [
        'amo_deal_id',
        'client_name',
        'deal_name',
        'total_amount',
        'responsible_user_id',
        'responsible_name',
        'status_id',
        'raw_payload',
    ];

    protected $casts = [
        'amo_deal_id' => 'integer',
        'total_amount' => 'float',
        'responsible_user_id' => 'integer',
        'status_id' => 'integer',
        'raw_payload' => 'array',
    ];
}

