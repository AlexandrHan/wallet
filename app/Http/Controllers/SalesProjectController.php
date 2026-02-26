<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\SalesProject;
use App\Models\CashTransfer;

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

        $advance = (float)($data['advance_amount'] ?? 0);
        $remaining = (float)$data['total_amount'] - $advance;

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

        // Якщо є аванс — створюємо pending transfer (старе/необов’язкове, залишаю як у тебе)
        if ($advance > 0 && $request->from_wallet_id && $request->to_wallet_id) {

            $transferCurrency = $data['currency'];
            $exchangeRate = null;
            $usdAmount = $advance;

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

                if ($transferCurrency === 'EUR') {
                    $usdAmount = round($advance * $exchangeRate, 2);
                } elseif ($transferCurrency === 'UAH') {
                    $usdAmount = round($advance / $exchangeRate, 2);
                }
            }

            CashTransfer::create([
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
        $projects = SalesProject::orderByDesc('id')->get()->map(function ($project) {

            $transfers = CashTransfer::where('project_id', $project->id)
                ->orderByDesc('id')
                ->get();

            $paid = (float)$transfers->where('status', 'accepted')->sum('usd_amount');
            $pending = (float)$transfers->where('status', 'pending')->sum('usd_amount');

            $pendingTargetOwner = $transfers
                ->where('status', 'pending')
                ->pluck('target_owner')
                ->filter()
                ->first();

            return [
                'id' => $project->id,
                'client_name' => $project->client_name,
                'total_amount' => (float)$project->total_amount,
                'paid_amount' => $paid,
                'pending_amount' => $pending,
                'remaining_amount' => (float)$project->total_amount - $paid,
                'currency' => $project->currency,
                'status' => $project->status,
                'created_at' => $project->created_at->format('d.m.Y H:i'),
                'pending_target_owner' => $pendingTargetOwner,
                'transfers' => $transfers->map(function ($t) {
                    return [
                        'id' => $t->id,
                        'amount' => (float)$t->amount,
                        'currency' => $t->currency,
                        'exchange_rate' => $t->exchange_rate,
                        'usd_amount' => (float)$t->usd_amount,
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
        $project = SalesProject::find($id);

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

        // USD -> USD
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

            if ($currency === 'EUR') {
                $usdAmount = round($amount * $exchangeRate, 2);
            } elseif ($currency === 'UAH') {
                $usdAmount = round($amount / $exchangeRate, 2);
            }
        }

        $user = auth()->user();

        // =========================
        // ✅ OWNER: 1 операція + 1 transfer accepted (БЕЗ ДУБЛІВ)
        // =========================
        if ($user && $user->role === 'owner') {

            $ownerWallet = DB::table('wallets')
                ->where('owner', $user->actor)
                ->where('currency', $currency)
                ->where('type', 'cash')
                ->first();

            if (!$ownerWallet) {
                return response()->json(['error' => 'Wallet not found'], 422);
            }

            $transfer = null;

            DB::transaction(function () use ($ownerWallet, $amount, $project, $currency, $exchangeRate, $usdAmount, $user, &$transfer) {

                // ✅ тільки ОДНА операція income
                DB::table('entries')->insert([
                    'wallet_id'    => $ownerWallet->id,
                    'entry_type'   => 'income',
                    'amount'       => $amount,
                    'comment'      => 'Аванс: ' . ($project->client_name ?? ''),
                    'posting_date' => date('Y-m-d'),
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);

                // ✅ transfer одразу accepted
                $transfer = CashTransfer::create([
                    'project_id'     => $project->id,
                    'from_wallet_id' => null,
                    'to_wallet_id'   => $ownerWallet->id,
                    'amount'         => $amount,
                    'currency'       => $currency,
                    'exchange_rate'  => $exchangeRate,
                    'usd_amount'     => $usdAmount,
                    'status'         => 'accepted',
                    'target_owner'   => $user->actor,
                    'created_by'     => $user->id,
                    'accepted_at'    => now(),
                ]);
            });

            return response()->json([
                'ok' => true,
                'transfer' => $transfer
            ]);
        }

        // =========================
        // 🔵 НЕ owner (НТВ): спочатку гроші падають у кеш НТВ, transfer pending
        // =========================
        $ntvWallet = DB::table('wallets')
            ->where('owner', $user->actor)
            ->where('type', 'cash')
            ->where('currency', $currency)
            ->first();

        if (!$ntvWallet) {
            $walletId = DB::table('wallets')->insertGetId([
                'name'       => 'КЕШ НТВ (' . $currency . ')',
                'currency'   => $currency,
                'type'       => 'cash',
                'owner'      => $user->actor,
                'is_active'  => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $ntvWallet = (object)['id' => $walletId];
        }

        // ✅ прихід у кеш НТВ (ОДИН раз)
        DB::table('entries')->insert([
            'wallet_id'    => $ntvWallet->id,
            'entry_type'   => 'income',
            'amount'       => $amount,
            'comment'      => 'Аванс: ' . ($project->client_name ?? ''),
            'posting_date' => date('Y-m-d'),
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        // ✅ pending transfer (щоб owner потім “прийняв”)
        $transfer = CashTransfer::create([
            'project_id'     => $project->id,
            'from_wallet_id' => $ntvWallet->id,
            'to_wallet_id'   => null,
            'amount'         => $amount,
            'currency'       => $currency,
            'exchange_rate'  => $exchangeRate,
            'usd_amount'     => $usdAmount,
            'status'         => 'pending',
            'created_by'     => $user->id,
        ]);

        return response()->json([
            'ok' => true,
            'transfer' => $transfer
        ]);
    }

    public function setTargetOwner(Request $request, $id)
    {
        $u = auth()->user();
        if (!$u || $u->role === 'owner') {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'target_owner' => 'required|in:hlushchenko,kolisnyk',
        ]);

        $project = SalesProject::find($id);
        if (!$project) {
            return response()->json(['error' => 'Проект не знайдено'], 404);
        }

        DB::table('cash_transfers')
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

        $project = SalesProject::find($id);
        if (!$project) {
            return response()->json(['error' => 'Проект не знайдено'], 404);
        }

        DB::table('cash_transfers')
            ->where('project_id', $project->id)
            ->where('status', 'pending')
            ->update([
                'target_owner' => null,
                'updated_at' => now(),
            ]);

        return response()->json(['ok' => true]);
    }
}