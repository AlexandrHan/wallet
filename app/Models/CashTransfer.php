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
        'created_by',
        'transfer_type',
        'employee_user_id',
        'comment',
        'cancelled_at',
        'cancelled_by',
    ];

    protected $casts = [
        'accepted_at'  => 'datetime',
        'cancelled_at' => 'datetime',
    ];
}
