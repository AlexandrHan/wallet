<?php

namespace App\Http\Controllers;

use App\Models\CashTransfer;
use App\Http\Controllers\CashTransferController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CashDeskController extends Controller
{
    public function acceptAll(Request $request)
    {
        $user = auth()->user();
        if (!$user || $user->role !== 'owner') {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $actor = (string) ($user->actor ?? '');
        if ($actor === '') {
            return response()->json(['error' => 'Actor not found'], 422);
        }

        $transfers = CashTransfer::query()
            ->where('status', 'pending')
            ->where('target_owner', $actor)
            ->whereNull('project_id')
            ->orderBy('id')
            ->get();

        if ($transfers->isEmpty()) {
            return response()->json([
                'ok' => true,
                'accepted' => [],
            ]);
        }

        $accepted = [];
        $acceptController = app(CashTransferController::class);

        DB::transaction(function () use ($transfers, $acceptController, &$accepted) {
            foreach ($transfers as $transfer) {
                if (!$transfer->to_wallet_id) {
                    $wallet = DB::table('wallets')
                        ->where('owner', $transfer->target_owner)
                        ->where('currency', $transfer->currency)
                        ->where('name', 'like', '%(' . $transfer->currency . ')')
                        ->first(['id']);

                    if (!$wallet) {
                        throw new \RuntimeException('Wallet not found for ' . $transfer->target_owner . ' ' . $transfer->currency);
                    }

                    $transfer->to_wallet_id = (int) $wallet->id;
                    $transfer->save();
                }

                $response = $acceptController->accept($transfer->id);
                $payload = json_decode($response->getContent(), true);
                $status = method_exists($response, 'status') ? $response->status() : 200;

                if ($status >= 400) {
                    $message = $payload['error'] ?? $payload['message'] ?? 'Accept failed';
                    throw new \RuntimeException($message);
                }

                $accepted[] = [
                    'id' => (int) $transfer->id,
                    'currency' => (string) $transfer->currency,
                    'amount' => (float) $transfer->amount,
                ];
            }
        });

        return response()->json([
            'ok' => true,
            'accepted' => $accepted,
        ]);
    }

    public function pendingList(Request $request)
    {
        $user = auth()->user();
        if (!$user || !in_array($user->role, ['owner', 'accountant'], true)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $actor = (string) ($user->actor ?? '');
        if ($actor === '') {
            return response()->json(['error' => 'Actor not found'], 422);
        }

        $transfers = DB::table('cash_transfers')
            ->where('status', 'pending')
            ->where('target_owner', $actor)
            ->whereNull('project_id')
            ->orderBy('id')
            ->get(['id', 'amount', 'currency']);

        return response()->json($transfers);
    }

    public function pendingSummary(Request $request)
    {
        $user = auth()->user();
        if (!$user || !in_array($user->role, ['owner', 'accountant'], true)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $actor = (string) ($user->actor ?? '');
        if ($actor === '') {
            return response()->json(['error' => 'Actor not found'], 422);
        }

        $totals = DB::table('cash_transfers')
            ->where('status', 'pending')
            ->where('target_owner', $actor)
            ->selectRaw('currency, SUM(amount) as total')
            ->groupBy('currency')
            ->pluck('total', 'currency');

        return response()->json([
            'USD' => round((float) ($totals['USD'] ?? 0), 2),
            'UAH' => round((float) ($totals['UAH'] ?? 0), 2),
            'EUR' => round((float) ($totals['EUR'] ?? 0), 2),
        ]);
    }

    public function submit(Request $request)
    {
        $user = auth()->user();
        if (!$user || $user->role !== 'accountant') {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'target_owner' => 'required|in:hlushchenko,kolisnyk',
        ]);

        $wallets = DB::table('wallets')
            ->where('owner', 'accountant')
            ->where(function ($query) {
                $query->where('name', 'like', '%(USD)%')
                    ->orWhere('name', 'like', '%(UAH)%')
                    ->orWhere('name', 'like', '%(EUR)%');
            })
            ->orderBy('id')
            ->get(['id', 'name', 'currency']);

        $created = [];
        $skipped = [];

        DB::transaction(function () use ($wallets, $data, $user, &$created, &$skipped) {
            foreach ($wallets as $wallet) {
                $balance = (float) DB::table('entries')
                    ->where('wallet_id', $wallet->id)
                    ->selectRaw("
                        COALESCE(SUM(
                            CASE
                                WHEN entry_type = 'income' THEN amount
                                WHEN entry_type = 'expense' THEN -amount
                                ELSE 0
                            END
                        ), 0) as balance
                    ")
                    ->value('balance');

                if ($balance <= 0) {
                    $skipped[] = [
                        'wallet_id' => (int) $wallet->id,
                        'currency' => (string) $wallet->currency,
                        'reason' => 'zero_balance',
                    ];
                    continue;
                }

                $alreadyPending = DB::table('cash_transfers')
                    ->where('from_wallet_id', $wallet->id)
                    ->where('currency', $wallet->currency)
                    ->where('target_owner', $data['target_owner'])
                    ->where('status', 'pending')
                    ->exists();

                if ($alreadyPending) {
                    $skipped[] = [
                        'wallet_id' => (int) $wallet->id,
                        'currency' => (string) $wallet->currency,
                        'reason' => 'pending_exists',
                    ];
                    continue;
                }

                $transfer = CashTransfer::create([
                    'from_wallet_id' => $wallet->id,
                    'to_wallet_id'   => null,
                    'amount'         => round($balance, 2),
                    'currency'       => $wallet->currency,
                    'status'         => 'pending',
                    'target_owner'   => $data['target_owner'],
                    'created_by'     => $user->id,
                    'comment'        => 'cash_submit',
                ]);

                $created[] = [
                    'id' => (int) $transfer->id,
                    'from_wallet_id' => (int) $transfer->from_wallet_id,
                    'amount' => (float) $transfer->amount,
                    'currency' => (string) $transfer->currency,
                    'target_owner' => (string) $transfer->target_owner,
                    'status' => (string) $transfer->status,
                ];
            }
        });

        return response()->json([
            'ok' => true,
            'created' => $created,
            'skipped' => $skipped,
        ]);
    }
}
