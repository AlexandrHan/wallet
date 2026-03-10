<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankTransactionRaw extends Model
{
    protected $table = 'bank_transactions_raw';

    protected $fillable = [
        'bank_code',
        'account_iban',
        'external_id',
        'hash',
        'operation_date',
        'dk',
        'amount',
        'currency',
        'counterparty',
        'purpose',
        'raw',
    ];

    protected $casts = [
        'raw'            => 'array',
        'operation_date' => 'date:Y-m-d',
        'amount'         => 'float',
        'dk'             => 'string',
    ];

}
