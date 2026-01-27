<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use Illuminate\View\View;

class WalletController extends Controller
{
    public function index(): View
    {
        $bankAccounts = BankAccount::where('is_active', true)->get();

        return view('wallet.index', [
            'bankAccounts' => $bankAccounts,
        ]);
    }
}


