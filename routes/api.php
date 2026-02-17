<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Http;
use App\Http\Controllers\EntryReceiptController;
use App\Services\ErpNextService;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\DeliveryController;







Route::get('/ping', fn () => response()->json(['ok' => true]));

// ❗ ПОКИ БЕЗ auth, ЩОБ НЕ ЗАВАЖАВ

Route::post('/entries/{entry}/receipt', [EntryReceiptController::class, 'store']);


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
                'signed_amount' => $signed,
                'title' => $e->title,
                'comment' => $e->comment,
                'created_by' => $e->created_by,

                // ✅ ДОДАЛИ
                'receipt_path' => $e->receipt_path,
                'receipt_url'  => $e->receipt_path ? Storage::disk('public')->url($e->receipt_path) : null,
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
    if ($entry->posting_date !== date('Y-m-d')) {
        return response('Редагування заборонено', 403);
    }

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

    if ($entry->posting_date !== date('Y-m-d')) {
        return response('Видалення заборонено', 403);
    }

    DB::table('entries')->where('id', $id)->delete();

    return ['ok' => true];
});


Route::post('/entries/{entry}/receipt', [EntryReceiptController::class, 'store']);

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


///////////////////////////////////. Санфікс склад.  /////////////////////////////////////

Route::get('/stock', [\App\Http\Controllers\StockController::class, 'index']);

Route::post('/deliveries', [\App\Http\Controllers\DeliveryController::class, 'store']);

Route::get('/deliveries', function () {
    return \Illuminate\Support\Facades\DB::table('supplier_deliveries')
        ->orderByDesc('id')
        ->get();
});

Route::get('/deliveries/{id}/items', function ($id) {
    return DB::table('supplier_delivery_items as items')
        ->join('products','products.id','=','items.product_id')
        ->where('items.delivery_id',$id)
        ->select(
            'products.name',
            'items.qty_declared',
            'items.qty_accepted',
            'items.supplier_price'
        )
        ->get();
});


Route::middleware(['web','auth','only.sunfix.manager'])
    ->delete('/deliveries/{id}', [DeliveryController::class, 'destroy']);


Route::post('/deliveries/{id}/items', [\App\Http\Controllers\DeliveryController::class, 'addItem']);


/** Категорії */
Route::get('/product-categories', function () {
    return DB::table('product_categories')
        ->select('id','name')
        ->orderBy('name')
        ->get();
});

/** Товари (active only за замовчуванням) */
Route::get('/products', function (Request $request) {
    $q = DB::table('products')
        ->leftJoin('product_categories', 'product_categories.id', '=', 'products.category_id')
        ->select(
            'products.id',
            'products.name',
            'products.category_id',
            'products.is_active',
            'product_categories.name as category_name'
        )
        ->orderBy('product_categories.name')
        ->orderBy('products.name');

    // якщо НЕ просимо include_inactive=1 — показуємо тільки активні
    if (!$request->boolean('include_inactive')) {
        $q->where('products.is_active', 1);
    }

    return $q->get();
});

/** Створити товар */
Route::post('/products', function (Request $request) {

    $name = trim((string)$request->input('name'));
    $categoryId = (int)$request->input('category_id');

    if ($name === '') {
        return response()->json(['error' => 'Назва обовʼязкова'], 422);
    }
    if ($categoryId <= 0) {
        return response()->json(['error' => 'Оберіть категорію'], 422);
    }

    $id = DB::table('products')->insertGetId([
        'supplier_id' => 1,                 // ✅ важливо, бо в тебе NOT NULL
        'sku' => uniqid('manual_'),
        'name' => $name,
        'category_id' => $categoryId,
        'currency' => 'USD',
        'supplier_price' => 0,
        'is_active' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return response()->json(['id' => $id]);
});

/** Оновити товар (назва/категорія/активність) */
Route::patch('/products/{id}', function (Request $request, $id) {

    $id = (int)$id;
    $name = trim((string)$request->input('name'));
    $categoryId = (int)$request->input('category_id');
    $isActive = $request->has('is_active') ? (int)!!$request->input('is_active') : null;

    if ($name === '') {
        return response()->json(['error' => 'Назва обовʼязкова'], 422);
    }
    if ($categoryId <= 0) {
        return response()->json(['error' => 'Оберіть категорію'], 422);
    }

    $payload = [
        'name' => $name,
        'category_id' => $categoryId,
        'updated_at' => now(),
    ];
    if ($isActive !== null) {
        $payload['is_active'] = $isActive;
    }

    DB::table('products')->where('id', $id)->update($payload);

    return response()->json(['ok' => true]);
});

/** “Видалити” без фізичного delete: в архів (щоб не ламати join в поставках) */
Route::delete('/products/{id}', function ($id) {
    $id = (int)$id;

    DB::table('products')->where('id', $id)->update([
        'is_active' => 0,
        'updated_at' => now(),
    ]);

    return response()->json(['ok' => true]);
});



Route::get('/deliveries/{id}', function ($id) {
    return DB::table('supplier_deliveries')
        ->where('id',$id)
        ->first();
});

Route::post('/deliveries/{id}/ship', [\App\Http\Controllers\DeliveryController::class, 'ship']);

Route::get('/deliveries', function () {
    return DB::table('supplier_deliveries')
        ->orderByDesc('id')
        ->get();
});

Route::get('/deliveries/{id}', [DeliveryController::class, 'get']);

Route::middleware(['web','auth'])->post('/deliveries/{id}/accept', [DeliveryController::class, 'accept']);

Route::get('/deliveries', [DeliveryController::class, 'indexApi']);

Route::get('/deliveries/{id}/items', [DeliveryController::class, 'items']);

Route::middleware('auth')->post('/supplier-cash/{id}/received', function ($id) {

    DB::table('supplier_cash_transfers')
        ->where('id', $id)
        ->update([
            'is_received' => 1,
            'received_by' => auth()->id(),
            'received_at' => now(),
            'updated_at' => now(),
        ]);

    return response()->json([
        'ok' => true
    ]);
});

Route::delete('/deliveries/items/{id}', [DeliveryController::class, 'deleteItem']);
