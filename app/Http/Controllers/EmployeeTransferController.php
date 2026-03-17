<?php

namespace App\Http\Controllers;

use App\Models\CashTransfer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmployeeTransferController extends Controller
{
    // POST /api/employee-transfers
    public function store(Request $request)
    {
        $user = auth()->user();
        if ($user->role !== 'owner') {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'employee_wallet_id' => 'required|integer|exists:wallets,id',
            'amount'             => 'required|numeric|min:0.01',
            'comment'            => 'nullable|string|max:500',
        ]);

        $employeeWallet = DB::table('wallets')->where('id', $data['employee_wallet_id'])->first();
        if (!$employeeWallet) {
            return response()->json(['error' => 'Гаманець не знайдено'], 404);
        }

        // Знайти кешевий гаманець власника з такою ж валютою
        $ownerWallet = DB::table('wallets')
            ->where('owner', $user->actor)
            ->where('currency', $employeeWallet->currency)
            ->where('type', 'cash')
            ->where('is_active', 1)
            ->first();

        if (!$ownerWallet) {
            return response()->json(['error' => 'Не знайдено кешевий гаманець власника в валюті ' . $employeeWallet->currency], 422);
        }

        // Перевірити баланс власника
        $sums = DB::table('entries')
            ->where('wallet_id', $ownerWallet->id)
            ->selectRaw("SUM(CASE WHEN entry_type='income' THEN amount ELSE 0 END) as income, SUM(CASE WHEN entry_type='expense' THEN amount ELSE 0 END) as expense")
            ->first();
        $balance = (float)($sums->income ?? 0) - (float)($sums->expense ?? 0);

        if ($balance < (float)$data['amount']) {
            return response()->json(['error' => 'Недостатньо коштів. Баланс: ' . number_format($balance, 2) . ' ' . $employeeWallet->currency], 422);
        }

        // Знайти user_id співробітника
        $employeeUser = DB::table('users')->where('actor', $employeeWallet->owner)->first();

        $transfer = CashTransfer::create([
            'from_wallet_id'   => $ownerWallet->id,
            'to_wallet_id'     => $employeeWallet->id,
            'amount'           => $data['amount'],
            'currency'         => $employeeWallet->currency,
            'status'           => 'pending',
            'transfer_type'    => 'employee',
            'employee_user_id' => $employeeUser?->id,
            'comment'          => $data['comment'] ?? null,
            'created_by'       => $user->id,
        ]);

        return response()->json([
            'success'  => true,
            'transfer' => $transfer,
        ]);
    }

    // GET /api/employee-transfers/pending
    public function pending()
    {
        $user = auth()->user();

        if ($user->role === 'owner') {
            $transfers = DB::table('cash_transfers as t')
                ->where('t.transfer_type', 'employee')
                ->where('t.status', 'pending')
                ->where('t.created_by', $user->id)
                ->join('wallets as w', 'w.id', '=', 't.to_wallet_id')
                ->select('t.*', 'w.name as employee_wallet_name', 'w.owner as employee_owner')
                ->orderByDesc('t.id')
                ->get();
        } else {
            $myWalletIds = DB::table('wallets')
                ->where('owner', $user->actor)
                ->where('is_active', 1)
                ->pluck('id');

            $transfers = DB::table('cash_transfers as t')
                ->where('t.transfer_type', 'employee')
                ->where('t.status', 'pending')
                ->whereIn('t.to_wallet_id', $myWalletIds)
                ->join('wallets as fw', 'fw.id', '=', 't.from_wallet_id')
                ->join('users as u', 'u.id', '=', 't.created_by')
                ->select('t.*', 'fw.name as from_wallet_name', 'u.name as sender_name')
                ->orderByDesc('t.id')
                ->get();
        }

        return response()->json($transfers);
    }

    // POST /api/employee-transfers/{id}/accept
    public function accept($id)
    {
        $user = auth()->user();

        $transfer = CashTransfer::find($id);
        if (!$transfer || $transfer->transfer_type !== 'employee') {
            return response()->json(['error' => 'Not found'], 404);
        }
        if ($transfer->status !== 'pending') {
            return response()->json(['error' => 'Вже оброблено'], 422);
        }

        // Перевірити що to_wallet_id належить поточному юзеру
        $toWallet = DB::table('wallets')->where('id', $transfer->to_wallet_id)->first();
        if (!$toWallet || $toWallet->owner !== $user->actor) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $fromWallet = DB::table('wallets')->where('id', $transfer->from_wallet_id)->first();
        $sender = DB::table('users')->where('id', $transfer->created_by)->first();
        $senderLabel = $sender?->name ?? $sender?->actor ?? 'Власник';
        $employeeLabel = $user->name ?? $user->actor ?? 'Співробітник';

        DB::transaction(function () use ($transfer, $fromWallet, $toWallet, $senderLabel, $employeeLabel) {
            $comment = $transfer->comment ? ' | ' . $transfer->comment : '';

            // Витрата у власника
            DB::table('entries')->insert([
                'wallet_id'       => $fromWallet->id,
                'entry_type'      => 'expense',
                'amount'          => $transfer->amount,
                'comment'         => 'Передано: ' . $employeeLabel . $comment,
                'posting_date'    => date('Y-m-d'),
                'cash_transfer_id'=> $transfer->id,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);

            // Дохід у співробітника
            DB::table('entries')->insert([
                'wallet_id'       => $toWallet->id,
                'entry_type'      => 'income',
                'amount'          => $transfer->amount,
                'comment'         => 'Отримано від: ' . $senderLabel . $comment,
                'posting_date'    => date('Y-m-d'),
                'cash_transfer_id'=> $transfer->id,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);

            $transfer->update([
                'status'      => 'accepted',
                'accepted_at' => now(),
            ]);
        });

        return response()->json(['success' => true]);
    }

    // POST /api/employee-transfers/{id}/decline
    public function decline($id)
    {
        $user = auth()->user();

        $transfer = CashTransfer::find($id);
        if (!$transfer || $transfer->transfer_type !== 'employee') {
            return response()->json(['error' => 'Not found'], 404);
        }
        if ($transfer->status !== 'pending') {
            return response()->json(['error' => 'Вже оброблено'], 422);
        }

        $toWallet = DB::table('wallets')->where('id', $transfer->to_wallet_id)->first();
        if (!$toWallet || $toWallet->owner !== $user->actor) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $transfer->update(['status' => 'declined']);

        return response()->json(['success' => true]);
    }

    // POST /api/employee-transfers/{id}/cancel
    public function cancel($id)
    {
        $user = auth()->user();

        if ($user->role !== 'owner') {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $transfer = CashTransfer::find($id);
        if (!$transfer || $transfer->transfer_type !== 'employee') {
            return response()->json(['error' => 'Not found'], 404);
        }
        if ($transfer->status !== 'accepted') {
            return response()->json(['error' => 'Можна скасувати тільки підтверджену операцію'], 422);
        }
        if ($transfer->created_by !== $user->id) {
            return response()->json(['error' => 'Forbidden'], 403);
        }
        if ($transfer->created_at->toDateString() !== now()->toDateString()) {
            return response()->json(['error' => 'Скасування можливе тільки в день операції'], 422);
        }

        $fromWallet = DB::table('wallets')->where('id', $transfer->from_wallet_id)->first();
        $toWallet   = DB::table('wallets')->where('id', $transfer->to_wallet_id)->first();
        $employee   = DB::table('wallets')->where('id', $transfer->to_wallet_id)->value('owner');
        $empUser    = DB::table('users')->where('actor', $employee)->first();
        $empLabel   = $empUser?->name ?? $employee ?? 'Співробітник';
        $ownerLabel = $user->name ?? $user->actor ?? 'Власник';

        DB::transaction(function () use ($transfer, $fromWallet, $toWallet, $empLabel, $ownerLabel, $user) {
            // Повернення власнику
            DB::table('entries')->insert([
                'wallet_id'       => $fromWallet->id,
                'entry_type'      => 'income',
                'amount'          => $transfer->amount,
                'comment'         => 'Скасовано передачу: ' . $empLabel,
                'posting_date'    => date('Y-m-d'),
                'cash_transfer_id'=> $transfer->id,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);

            // Списання у співробітника
            DB::table('entries')->insert([
                'wallet_id'       => $toWallet->id,
                'entry_type'      => 'expense',
                'amount'          => $transfer->amount,
                'comment'         => 'Скасовано: повернення коштів ' . $ownerLabel,
                'posting_date'    => date('Y-m-d'),
                'cash_transfer_id'=> $transfer->id,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);

            $transfer->update([
                'status'       => 'cancelled',
                'cancelled_at' => now(),
                'cancelled_by' => $user->id,
            ]);
        });

        return response()->json(['success' => true]);
    }

    // GET /api/employee-transfers/history
    public function history()
    {
        $user = auth()->user();

        if ($user->role === 'owner') {
            $transfers = DB::table('cash_transfers as t')
                ->where('t.transfer_type', 'employee')
                ->where('t.created_by', $user->id)
                ->join('wallets as w', 'w.id', '=', 't.to_wallet_id')
                ->leftJoin('users as eu', 'eu.id', '=', 't.employee_user_id')
                ->select('t.*', 'w.name as employee_wallet_name', 'w.owner as employee_owner', 'eu.name as employee_name')
                ->orderByDesc('t.id')
                ->limit(50)
                ->get();
        } else {
            $myWalletIds = DB::table('wallets')
                ->where('owner', $user->actor)
                ->where('is_active', 1)
                ->pluck('id');

            $transfers = DB::table('cash_transfers as t')
                ->where('t.transfer_type', 'employee')
                ->whereIn('t.to_wallet_id', $myWalletIds)
                ->join('users as u', 'u.id', '=', 't.created_by')
                ->select('t.*', 'u.name as sender_name')
                ->orderByDesc('t.id')
                ->limit(50)
                ->get();
        }

        return response()->json($transfers);
    }
}
