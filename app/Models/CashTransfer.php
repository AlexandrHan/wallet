<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashTransfer extends Model
{
    protected $fillable = [
        'project_id',
        'from_wallet_id',
        'to_wallet_id',
        'amount',
        'currency',
        'exchange_rate',
        'usd_amount',
        'status',
        'created_by'
    ];
}
