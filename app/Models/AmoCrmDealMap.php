<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AmoCrmDealMap extends Model
{
    protected $table = 'amocrm_deal_map';

    public $timestamps = false;

    const UPDATED_AT = null;

    protected $fillable = [
        'amo_deal_id',
        'wallet_project_id',
        'created_at',
    ];

    protected $casts = [
        'amo_deal_id' => 'integer',
        'wallet_project_id' => 'integer',
        'created_at' => 'datetime',
    ];
}
