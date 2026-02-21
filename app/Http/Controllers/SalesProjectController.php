<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SalesProject;

class SalesProjectController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'client_name'    => 'required|string',
            'total_amount'   => 'required|numeric|min:0.01',
            'advance_amount' => 'nullable|numeric|min:0',
            'currency'       => 'required|in:UAH,USD,EUR',
            'from_wallet_id' => 'nullable|integer',
            'to_wallet_id'   => 'nullable|integer',
        ]);

        $advance = $data['advance_amount'] ?? 0;

        $remaining = $data['total_amount'] - $advance;

        if ($remaining < 0) {
            return response()->json([
                'error' => 'Аванс не може бути більший за суму проекту'
            ], 422);
        }

        $project = SalesProject::create([
            'client_name'      => $data['client_name'],
            'total_amount'     => $data['total_amount'],
            'advance_amount'   => $advance,
            'remaining_amount' => $remaining,
            'currency'         => $data['currency'],
            'created_by'       => auth()->id(),
            'status'           => 'active',
        ]);
        
        // Якщо є аванс — створюємо pending transfer
        if ($advance > 0 && $request->from_wallet_id && $request->to_wallet_id) {

        $transferCurrency = $data['currency'];
        $exchangeRate = null;
        $usdAmount = $advance;

        // якщо проект в USD, але аванс в іншій валюті
        if ($transferCurrency !== 'USD') {

            if (!$request->exchange_rate) {
                return response()->json([
                    'error' => 'Потрібно ввести курс для перерахунку в USD'
                ], 422);
            }

            $exchangeRate = (float)$request->exchange_rate;

            if ($exchangeRate <= 0) {
                return response()->json([
                    'error' => 'Некоректний курс'
                ], 422);
            }

            $usdAmount = round($advance / $exchangeRate, 2);
        }

        \App\Models\CashTransfer::create([
            'project_id'     => $project->id,
            'from_wallet_id' => $request->from_wallet_id,
            'to_wallet_id'   => $request->to_wallet_id,
            'amount'         => $advance,
            'currency'       => $transferCurrency,
            'exchange_rate'  => $exchangeRate,
            'usd_amount'     => $usdAmount,
            'status'         => 'pending',
            'created_by'     => auth()->id(),
        ]);
        }

        return response()->json([
            'ok' => true,
            'project' => $project
        ]);
    }

    public function index()
    {
        $projects = \App\Models\SalesProject::orderByDesc('id')->get()->map(function ($project) {

            $transfers = \App\Models\CashTransfer::where('project_id', $project->id)
                ->orderByDesc('id')
                ->get();

            $paid = $transfers->where('status', 'accepted')->sum('usd_amount');
            $pending = $transfers->where('status', 'pending')->sum('usd_amount');

            $pendingTargetOwner = $transfers
                ->where('status', 'pending')
                ->pluck('target_owner')
                ->filter()
                ->first(); // якщо НТО вже вибрав власника — тут буде actor

            return [
                'id' => $project->id,
                'client_name' => $project->client_name,
                'total_amount' => $project->total_amount,
                'paid_amount' => $paid,
                'pending_amount' => $pending,
                'remaining_amount' => $project->total_amount - $paid,
                'currency' => $project->currency,
                'status' => $project->status,
                'created_at' => $project->created_at->format('d.m.Y H:i'),
                'pending_target_owner' => $pendingTargetOwner,
                'transfers' => $transfers->map(function ($t) {
                    return [
                        'id' => $t->id,
                        'amount' => $t->amount,
                        'currency' => $t->currency,          // ✅ додаємо
                        'exchange_rate' => $t->exchange_rate, // ✅ додаємо
                        'usd_amount' => $t->usd_amount,      // ✅ додаємо
                        'status' => $t->status,
                        'target_owner' => $t->target_owner,
                        'created_at' => \Carbon\Carbon::parse($t->created_at)->format('d.m.Y H:i'),
                    ];
                })->values(),
            ];
        });

        return response()->json($projects);
    }

    public function addAdvance(Request $request, $id)
    {
        $project = \App\Models\SalesProject::find($id);

        if (!$project) {
            return response()->json(['error' => 'Проект не знайдено'], 404);
        }

        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|in:USD,UAH,EUR',
            'exchange_rate' => 'nullable|numeric|min:0.000001'
        ]);

        $amount = (float)$data['amount'];
        $currency = $data['currency'];
        $exchangeRate = null;
        $usdAmount = null;

        // якщо аванс в USD
        if ($currency === 'USD') {
            $usdAmount = $amount;
        } else {

            if (!$data['exchange_rate']) {
                return response()->json([
                    'error' => 'Потрібно вказати курс для перерахунку в USD'
                ], 422);
            }

            $exchangeRate = (float)$data['exchange_rate'];

            if ($exchangeRate <= 0) {
                return response()->json([
                    'error' => 'Некоректний курс'
                ], 422);
            }

            // перерахунок в USD
            $usdAmount = round($amount / $exchangeRate, 2);
        }

        $u = auth()->user();
        $ownerActor = $u->actor;

        // 1) знайти або створити кеш НТО по валюті авансу
        $wallet = \Illuminate\Support\Facades\DB::table('wallets')
            ->where('owner', $ownerActor)
            ->where('type', 'cash')
            ->where('currency', $currency)
            ->first();

        if (!$wallet) {
            $walletId = \Illuminate\Support\Facades\DB::table('wallets')->insertGetId([
                'name' => 'КЕШ НТО (' . $currency . ')',
                'currency' => $currency,
                'type' => 'cash',
                'owner' => $ownerActor,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $wallet = (object)['id' => $walletId];
        }

        // 2) одразу записуємо прихід у кеш НТО (операції)
        \Illuminate\Support\Facades\DB::table('entries')->insert([
            'wallet_id'    => $wallet->id,
            'entry_type'   => 'income',
            'amount'       => $amount,
            'comment'      => 'Аванс: ' . $project->client_name,
            'posting_date' => date('Y-m-d'),
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        // створюємо transfer
        $transfer = \App\Models\CashTransfer::create([
            'project_id' => $project->id,
            'from_wallet_id' => $wallet->id,
            'to_wallet_id' => null,
            'amount' => $amount,
            'currency' => $currency,
            'exchange_rate' => $exchangeRate,
            'usd_amount' => $usdAmount,
            'status' => 'pending',
            'created_by' => auth()->id(),
        ]);

        return response()->json([
            'ok' => true,
            'transfer' => $transfer
        ]);
    }

    public function setTargetOwner(Request $request, $id)
    {
        // НТО/менеджер задає кому здає. Owner тут не потрібен.
        $u = auth()->user();
        if (!$u || $u->role === 'owner') {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'target_owner' => 'required|in:hlushchenko,kolisnyk',
        ]);

        $project = \App\Models\SalesProject::find($id);
        if (!$project) {
            return response()->json(['error' => 'Проект не знайдено'], 404);
        }

        // Ставимо target_owner для ВСІХ pending-авансів цього проекту
        \Illuminate\Support\Facades\DB::table('cash_transfers')
            ->where('project_id', $project->id)
            ->where('status', 'pending')
            ->update([
                'target_owner' => $data['target_owner'],
                'updated_at' => now(),
            ]);

        return response()->json(['ok' => true]);
    }

    public function cancelTargetOwner(Request $request, $id)
    {
        $u = auth()->user();
        if (!$u || $u->role === 'owner') {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $project = \App\Models\SalesProject::find($id);
        if (!$project) {
            return response()->json(['error' => 'Проект не знайдено'], 404);
        }

        // скидаємо вибір власника для всіх pending авансів цього проекту
        \Illuminate\Support\Facades\DB::table('cash_transfers')
            ->where('project_id', $project->id)
            ->where('status', 'pending')
            ->update([
                'target_owner' => null,
                'updated_at' => now(),
            ]);

        return response()->json(['ok' => true]);
}
}