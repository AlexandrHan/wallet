<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CashTransfer;
use Illuminate\Support\Facades\DB;

class CashTransferController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'from_wallet_id' => 'required|integer',
            'to_wallet_id'   => 'required|integer',
            'amount'         => 'required|numeric|min:0.01',
            'currency'       => 'required|string|size:3',
        ]);

        $transfer = CashTransfer::create([
            'from_wallet_id' => $request->from_wallet_id,
            'to_wallet_id'   => $request->to_wallet_id,
            'amount'         => $request->amount,
            'currency'       => $request->currency,
            'status'         => 'pending',
            'created_by'     => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'transfer' => $transfer
        ]);
    }
    public function accept($id)
    {
        $transfer = CashTransfer::find($id);

        if (!$transfer) {
            return response()->json(['error' => 'Not found'], 404);
        }

        if ($transfer->status !== 'pending') {
            return response()->json(['error' => 'Already processed'], 422);
        }

        $user = auth()->user();

        // 🟢 Якщо це аванс проекту
        if ($transfer->project_id) {

            if ($user->role !== 'owner') {
                return response()->json(['error' => 'Forbidden'], 403);
            }

            DB::transaction(function () use ($transfer) {

                // 1️⃣ Знаходимо кеш власника по валюті
                $wallet = DB::table('wallets')
                    ->where('owner', auth()->user()->actor)
                    ->where('currency', $transfer->currency)
                    ->first();

                if (!$wallet) {
                    throw new \Exception('Wallet not found');
                }

                // 2️⃣ Додаємо income в кеш
                DB::table('entries')->insert([
                    'wallet_id'    => $wallet->id,
                    'entry_type'   => 'income',
                    'amount'       => $transfer->amount,
                    'comment'      => 'Аванс по проекту #' . $transfer->project_id,
                    'posting_date' => date('Y-m-d'),
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);

                // 3️⃣ Статус
                $transfer->update([
                    'status' => 'accepted',
                    'to_wallet_id' => $wallet->id,
                    'accepted_at' => now(),
                ]);
            });

            return response()->json(['success' => true]);
        }

        // 🔵 Звичайний cash transfer (стара логіка)

        $wallet = DB::table('wallets')->where('id', $transfer->to_wallet_id)->first();

        if (!$wallet || $wallet->owner !== $user->actor) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        DB::transaction(function () use ($transfer) {

            DB::table('entries')->insert([
                'wallet_id'    => $transfer->from_wallet_id,
                'entry_type'   => 'expense',
                'amount'       => $transfer->amount,
                'comment'      => 'Передача коштів',
                'posting_date' => date('Y-m-d'),
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);

            DB::table('entries')->insert([
                'wallet_id'    => $transfer->to_wallet_id,
                'entry_type'   => 'income',
                'amount'       => $transfer->amount,
                'comment'      => 'Прийнято переказ',
                'posting_date' => date('Y-m-d'),
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);

            $transfer->update([
                'status' => 'accepted',
                'accepted_at' => now(),
            ]);
        });

        return response()->json(['success' => true]);
    }
    public function sendProjectMoney(Request $request)
    {
        $data = $request->validate([
            'project_id' => 'required|integer',
            'target' => 'required|in:hlushchenko,kolisnyk',
            'amount' => 'required|numeric|min:0.01'
        ]);

        $project = \App\Models\SalesProject::find($data['project_id']);
        if (!$project) {
            return response()->json(['error' => 'Project not found'], 404);
        }

        $currency = $project->currency;

        // 1️⃣ Знаходимо кеш власника
        $fromWallet = DB::table('wallets')
            ->where('owner', auth()->user()->actor)
            ->where('currency', $currency)
            ->first();

        // 2️⃣ Знаходимо кеш отримувача
        $toWallet = DB::table('wallets')
            ->where('owner', $data['target'])
            ->where('currency', $currency)
            ->first();

        if (!$fromWallet || !$toWallet) {
            return response()->json(['error' => 'Wallet not found'], 422);
        }

        $transfer = CashTransfer::create([
            'from_wallet_id' => $fromWallet->id,
            'to_wallet_id' => $toWallet->id,
            'amount' => $data['amount'],
            'currency' => $currency,
            'status' => 'pending',
            'created_by' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'transfer' => $transfer
        ]);
    }
}