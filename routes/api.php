<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Http;

Route::get('/ping', fn () => response()->json(['ok' => true]));

// ❗ ПОКИ БЕЗ auth, ЩОБ НЕ ЗАВАЖАВ


use App\Services\ErpNextService;

Route::post('/entries', function (Request $request) {

    $data = $request->validate([
        'wallet_id'  => 'required|integer',
        'entry_type' => 'required|in:income,expense',
        'amount'     => 'required|numeric|min:0.01',
        'comment'    => 'nullable|string',
    ]);


    $id = DB::table('entries')->insertGetId([
        'wallet_id'    => $data['wallet_id'],
        'entry_type'   => $data['entry_type'],
        'amount'       => $data['amount'],
        'comment'      => $data['comment'] ?? null,
        'posting_date' => date('Y-m-d'),
        'erp_sync_date'=> date('Y-m-d'),
        'erp_synced_at'=> null,
        'created_at'   => now(),
        'updated_at'   => now(),
    ]);


    // ⬅️ ОЦЕГО РАНІШЕ НЕ БУЛО
    try {
        app(\App\Services\ErpNextService::class)->syncEntry($id);
    } catch (\Throwable $e) {
        \Log::error('ERP sync failed', [
            'entry_id' => $id,
            'error' => $e->getMessage(),
        ]);
    }



    return response()->json([
        'id' => $id,
        'ok' => true,
    ]);
});




Route::get('/wallets', function () {

    $wallets = DB::table('wallets')
        ->where('is_active', 1)
        ->orderBy('owner')
        ->orderBy('currency')
        ->orderBy('name')
        ->get();

    $sums = DB::table('entries')
        ->select(
            'wallet_id',
            DB::raw("SUM(CASE WHEN entry_type = 'income' THEN amount ELSE 0 END) as income"),
            DB::raw("SUM(CASE WHEN entry_type = 'expense' THEN amount ELSE 0 END) as expense")
        )
        ->groupBy('wallet_id')
        ->get()
        ->keyBy('wallet_id');

    return $wallets->map(function ($w) use ($sums) {
        $row = $sums->get($w->id);

        return [
            'id'       => $w->id,
            'name'     => $w->name,
            'currency' => $w->currency,
            'owner'    => $w->owner,
            'balance'  => ($row->income ?? 0) - ($row->expense ?? 0),
        ];
    })->values();
});



Route::get('/wallets/{walletId}/entries', function (int $walletId) {

    $wallet = DB::table('wallets')
        ->where('id', $walletId)
        ->where('is_active', 1)
        ->first();

    if (! $wallet) {
        return response()->json(['message' => 'Wallet not found'], 404);
    }

    $entries = DB::table('entries')
        ->where('wallet_id', $walletId)
        ->orderByDesc('posting_date')
        ->orderByDesc('id')
        ->get()
        ->map(function ($e) {

            $signed = $e->entry_type === 'income'
                ? (float)$e->amount
                : (float)$e->amount * -1;

            return [
                'id' => (int)$e->id,
                'posting_date' => $e->posting_date,
                'entry_type' => $e->entry_type,
                'amount' => (float)$e->amount,
                'signed_amount' => $signed, // 🔥 КЛЮЧОВЕ
                'title' => $e->title,
                'comment' => $e->comment,
                'created_by' => $e->created_by,
            ];
        });


    return response()->json([
        'wallet' => [
            'id' => (int)$wallet->id,
            'name' => $wallet->name,
            'currency' => $wallet->currency,
            'owner' => $wallet->owner,
        ],
        'entries' => $entries,
    ]);
});






Route::delete('/wallets/{walletId}', function (int $walletId) {

    $wallet = DB::table('wallets')->where('id', $walletId)->first();

    if (! $wallet) {
        return response()->json(['message' => 'Wallet not found'], 404);
    }

    DB::table('wallets')
        ->where('id', $walletId)
        ->update([
            'is_active' => 0,
            'updated_at' => now(),
        ]);

    return response()->json([
        'ok' => true,
    ]);
});


function erpCashAccount(string $owner, string $currency): string
{
    $ownerName = match ($owner) {
        'kolisnyk' => 'Колісник',
        'hlushchenko' => 'Глущенко',
        default => throw new Exception('Unknown owner'),
    };

    return "{$currency} {$ownerName} КЕШ - SGH";
}



Route::put('/entries/{id}', function (int $id, \Illuminate\Http\Request $request) {

    $entry = DB::table('entries')->where('id', $id)->first();

    if (! $entry) {
        return response()->json(['message' => 'Entry not found'], 404);
    }

    // ❌ Заборона редагування не сьогоднішніх
    if ($entry->posting_date !== now()->toDateString()) {
        return response()->json([
            'message' => 'Редагування дозволено тільки в день створення'
        ], 403);
    }

    $data = $request->validate([
        'amount'  => 'required|numeric|min:0.01',
        'comment' => 'nullable|string',
    ]);

    DB::table('entries')
        ->where('id', $id)
        ->update([
            'amount'     => $data['amount'],
            'comment'    => $data['comment'],
            'updated_at' => now(),
        ]);

    return response()->json(['ok' => true]);
});



Route::delete('/entries/{id}', function (int $id) {

    $entry = DB::table('entries')->where('id', $id)->first();

    if (! $entry) {
        return response()->json(['message' => 'Entry not found'], 404);
    }

    // /////❌ Заборона видалення не сьогоднішніх
    if ($entry->posting_date !== now()->toDateString()) {
        return response()->json([
            'message' => 'Видалення дозволено тільки в день створення'
        ], 403);
    }

    DB::table('entries')->where('id', $id)->delete();

    return response()->json(['ok' => true]);
});


Route::put('/entries/{id}', function (Request $request, int $id) {

    $entry = DB::table('entries')->where('id', $id)->first();
    if (!$entry) {
        return response('Not found', 404);
    }

    // ❌ НЕ МОЖНА редагувати не сьогоднішні
    // if ($entry->posting_date !== date('Y-m-d')) {
    //     return response('Редагування заборонено', 403);
    // }

    DB::table('entries')->where('id', $id)->update([
        'amount'        => $request->amount,
        'comment'       => $request->comment,
        'erp_synced_at' => null, // ⬅️ ОБОВʼЯЗКОВО
        'updated_at'    => now(),
    ]);

    return ['ok' => true];
}); 


Route::delete('/entries/{id}', function (int $id) {

    $entry = DB::table('entries')->where('id', $id)->first();
    if (!$entry) {
        return response('Not found', 404);
    }

    // if ($entry->posting_date !== date('Y-m-d')) {
    //     return response('Видалення заборонено', 403);
    // }

    DB::table('entries')->where('id', $id)->delete();

    return ['ok' => true];
});

///////////////////////////////////. Видалення картки рахунку.  /////////////////////////////////////
use App\Models\BankAccount;

Route::delete('/accounts/{account}', function (BankAccount $account) {
    $account->delete();
    return response()->noContent();
});



///////////////////////////////////. Курс валют.  /////////////////////////////////////

Route::get('/exchange-rates', function () {

    // сьогоднішня дата в форматі ДД.ММ.РРРР
    $date = now()->format('d.m.Y');

    $url = "https://api.privatbank.ua/p24api/exchange_rates?json&date={$date}";

    $response = Http::get($url);

    if (!$response->ok()) {
        return response()->json(['error' => 'Failed to fetch rates'], 500);
    }

    $data = $response->json();

    // фільтруємо суто USD та EUR
    $rates = collect($data['exchangeRate'] ?? [])
        ->filter(fn($r) => in_array($r['currency'] ?? '', ['USD', 'EUR']))
        ->map(fn($r) => [
            'currency' => $r['currency'],
            'purchase' => $r['purchaseRate'] ?? null,
            'sale'     => $r['saleRate'] ?? null,
        ])
        ->values();

    return response()->json([
        'date'  => $date,
        'rates' => $rates,
    ]);
});
