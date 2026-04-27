<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AmoComplectationProject extends Model
{
    protected $table = 'amo_complectation_projects';

    protected $fillable = [
        'amo_deal_id',
        'wallet_project_id',
        'client_name',
        'deal_name',
        'total_amount',
        'pipeline_id',
        'currency',
        'responsible_user_id',
        'responsible_name',
        'status_id',
        'raw_payload',
        'won_at',
        'amo_closed_at',
    ];

    protected $casts = [
        'amo_deal_id' => 'integer',
        'wallet_project_id' => 'integer',
        'total_amount' => 'float',
        'pipeline_id' => 'integer',
        'responsible_user_id' => 'integer',
        'status_id' => 'integer',
        'raw_payload' => 'array',
        'amo_closed_at' => 'datetime',
    ];
}
