<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Entry extends Model
{
    protected $fillable = [
        'wallet_id',
        'signed_amount',
        'comment',
        'posting_date',
        'is_locked',
        'cash_transfer_id',
    ];
}