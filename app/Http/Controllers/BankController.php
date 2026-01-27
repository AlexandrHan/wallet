<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BankController extends Controller
{
    public function transactions($accountId)
    {
        $iban = preg_replace('/\s+/', '', $accountId);

        return DB::table('bank_transactions_raw')
            ->whereRaw(
                "REPLACE(account_iban, ' ', '') = ?",
                [$iban]
            )
            ->orderBy('operation_date', 'desc')
            ->get();
    }
}

