<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\Wallet;
use Illuminate\Http\Request;
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

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string',
            'currency' => 'required|in:UAH,USD,EUR',
        ]);

        $owner = auth()->user()->actor;

        $wallet = Wallet::create([
            'name' => $data['name'],
            'currency' => $data['currency'],
            'type' => 'cash',
            'owner' => $owner,
            'is_active' => 1,
        ]);

        return response()->json([
            'id' => $wallet->id,
            'owner' => $owner,
        ]);
    }
}


