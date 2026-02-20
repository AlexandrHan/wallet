<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\ReclamationController;
use App\Http\Controllers\FemDebtController;


use App\Models\BankTransactionRaw;

Route::middleware(['auth', 'only.reclamations', 'only.sunfix.manager'])->group(function () {


    /*
    |--------------------------------------------------------------------------
    | WEB pages
    |--------------------------------------------------------------------------
    */



    Route::get('/', function () {
        $bankAccounts = \App\Models\BankAccount::where('is_active', true)->get();

        return view('wallet', [
            'bankAccounts' => $bankAccounts,
        ]);
    })->name('home');

    // Breeze після логіну веде на dashboard, а ми перекидаємо на /
    Route::get('/dashboard', fn () => redirect('/'))->name('dashboard');

    // Налаштування (якщо хочеш окрему сторінку)
    Route::get('/settings', fn () => view('dashboard'))->name('settings');

    // Wallet page (як у тебе)
    Route::get('/wallet', [WalletController::class, 'index'])->name('wallet.index');


    /*
    |--------------------------------------------------------------------------
    | Profile (Breeze)
    |--------------------------------------------------------------------------
    */
    Route::prefix('profile')->group(function () {
        // можна і без prefix, але так компактніше; маршрути залишаться /profile
    });

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');


    /*
    |--------------------------------------------------------------------------
    | API (у тебе воно в web.php і з auth, лишаємо так само)
    |--------------------------------------------------------------------------
    */
    Route::prefix('api')->group(function () {

        // create cash wallet
        Route::post('/wallets', function (Request $request) {

            $data = $request->validate([
                'name' => 'required|string',
                'currency' => 'required|in:UAH,USD,EUR',
            ]);

            $owner = auth()->user()->actor;

            $id = DB::table('wallets')->insertGetId([
                'name' => $data['name'],
                'currency' => $data['currency'],
                'type' => 'cash',
                'owner' => $owner,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return response()->json([
                'id' => $id,
                'owner' => $owner,
            ]);
        });

        Route::get('/stock', function (Request $request) {

            // ====== 1) Діапазон (за замовчуванням поточний тиждень ПН-НД) ======
            $from = $request->query('from');
            $to   = $request->query('to');

            if (!$from || !$to) {
                $now = \Carbon\Carbon::now();
                $from = $now->copy()->startOfWeek(\Carbon\Carbon::MONDAY)->toDateString();
                $to   = $now->copy()->startOfWeek(\Carbon\Carbon::MONDAY)->addDays(6)->toDateString();
            }

            // ====== 2) Прийнято (тільки accepted) ======
            $purchases = DB::table('supplier_delivery_items as i')
                ->join('supplier_deliveries as d', 'd.id', '=', 'i.delivery_id')
                ->where('d.status', 'accepted')
                ->groupBy('i.product_id')
                ->select(
                    'i.product_id',
                    DB::raw('SUM(i.qty_accepted) as received'),
                    DB::raw('SUM(i.qty_accepted * i.supplier_price) as received_cost'),
                    DB::raw('ROUND(SUM(i.qty_accepted * i.supplier_price) / NULLIF(SUM(i.qty_accepted),0), 2) as avg_purchase_price')
                );

            // ====== 3) Продано (всього) + собівартість проданого (всього) ======
            $salesAll = DB::table('sales')
                ->groupBy('product_id')
                ->select(
                    'product_id',
                    DB::raw('SUM(qty) as sold'),
                    DB::raw('SUM(qty * supplier_price) as sold_cost')
                );

            // ====== 4) Віддаємо склад + зважену собівартість залишку ======
            $rows = DB::table('products as p')
                ->leftJoinSub($purchases, 'pur', 'pur.product_id', '=', 'p.id')
                ->leftJoinSub($salesAll, 's', 's.product_id', '=', 'p.id')
                ->whereNotNull('pur.product_id') // показуємо тільки те, що хоч раз приймали
                ->select(
                    'p.id as product_id',
                    'p.name',
                    DB::raw('COALESCE(pur.received,0) as received'),
                    DB::raw('COALESCE(s.sold,0) as sold'),
                    DB::raw('(COALESCE(pur.received,0) - COALESCE(s.sold,0)) as qty_on_stock'),

                    // ✅ собівартість одиниці залишку (moving weighted average)
                    DB::raw("
                        CASE
                        WHEN (COALESCE(pur.received,0) - COALESCE(s.sold,0)) > 0
                        THEN ROUND(
                            (COALESCE(pur.received_cost,0) - COALESCE(s.sold_cost,0))
                            / NULLIF((COALESCE(pur.received,0) - COALESCE(s.sold,0)), 0)
                        , 2)
                        ELSE ROUND(COALESCE(pur.avg_purchase_price,0), 2)
                        END as supplier_price
                    "),

                    // ✅ вартість залишку на складі
                    DB::raw('ROUND((COALESCE(pur.received_cost,0) - COALESCE(s.sold_cost,0)), 2) as stock_value')
                )
                ->orderBy('p.name')
                ->get();

            // ====== 5) Борг постачальнику за період = продажі - отримані кошти ======

            // 5.1) Продажі за період (sold_at або created_at якщо sold_at null)
            $salesPeriod = (float) DB::table('sales')
                ->whereBetween(DB::raw("date(COALESCE(sold_at, created_at))"), [$from, $to])
                ->sum(DB::raw('qty * supplier_price'));

            // 5.2) Отримані кошти за період (received_at)
            $paidPeriod = (float) DB::table('supplier_cash_transfers')
                ->where('is_received', 1)
                ->where('currency', 'USD')
                ->whereNotNull('received_at')
                ->whereBetween(DB::raw("date(received_at)"), [$from, $to])
                ->sum('amount');

            // 5.3) Нетто-борг
            $supplierDebt = max(0, $salesPeriod - $paidPeriod);

            // ====== BALANCE CHECK ======

            $soldCostTotal = (float) DB::table('sales')
                ->sum(DB::raw('qty * supplier_price'));

            $paidTotal = (float) DB::table('supplier_cash_transfers')
                ->where('is_received', 1)
                ->sum('amount');

            $balanceOk = abs($soldCostTotal - ($paidTotal + $supplierDebt)) < 0.01;


            return response()->json([
                'from' => $from,
                'to' => $to,
                'supplier_debt' => round($supplierDebt, 2),
                'balance_ok' => $balanceOk,
                'stock' => $rows,
            ]);


        });



        // ======================
        // SUPPLIER CASH
        // ======================

        Route::get('/supplier-cash', function (Request $request) {

            $rows = DB::table('supplier_cash_transfers')
                ->orderByDesc('id')
                ->limit(50)
                ->get();

            return response()->json($rows);
        });

        Route::post('/supplier-cash', function (Request $request) {

            $u = $request->user();

            $amount = (float) $request->input('amount', 0);

            if ($amount <= 0) {
                return response()->json(['error' => 'amount required'], 422);
            }

            $id = DB::table('supplier_cash_transfers')->insertGetId([
                'amount' => $amount,
                'created_by' => $u->id,
                'is_received' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return response()->json([
                'ok' => true,
                'id' => $id
            ]);
        });


        Route::post('/supplier-cash/{id}/received', function ($id) {

            // ✅ тільки менеджер має право "отримати"
            $u = auth()->user();
            if (!$u || $u->role !== 'sunfix_manager') {
                return response()->json(['error' => 'Forbidden'], 403);
            }

            $transfer = DB::table('supplier_cash_transfers')->where('id', $id)->first();
            if (!$transfer) {
                return response()->json(['error' => 'Not found'], 404);
            }

            // якщо вже отримано — просто ок
            if ((int)($transfer->is_received ?? 0) === 1) {
                return response()->json(['ok' => true, 'already' => true]);
            }

            DB::table('supplier_cash_transfers')
                ->where('id', $id)
                ->update([
                    'is_received' => 1,
                    'received_by' => $u->id,
                    'received_at' => now(),
                    'updated_at'  => now(),
                ]);

            return response()->json(['ok' => true]);
        });



        Route::post('/sales/batch', function (Request $request) {

            $u = $request->user();
            if (!$u || !in_array($u->role, ['owner', 'accountant'], true)) {
                return response()->json(['error' => 'Forbidden'], 403);
            }

            $data = $request->validate([
                'sold_at' => 'required|date',
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required|integer',
                'items.*.qty' => 'required|integer|min:0',
            ]);

            $soldAt = $data['sold_at'];

            try {

                DB::transaction(function () use ($data, $u, $soldAt) {

                    foreach ($data['items'] as $it) {

                        $pid = (int)$it['product_id'];
                        $qty = (int)$it['qty'];

                        if ($qty <= 0) continue;

                        // ціна постачальника
                        $supplierPrice = DB::table('supplier_delivery_items as i')
                            ->join('supplier_deliveries as d', 'd.id', '=', 'i.delivery_id')
                            ->where('d.status', 'accepted')
                            ->where('i.product_id', $pid)
                            ->orderByDesc('d.accepted_at')
                            ->orderByDesc('i.updated_at')
                            ->value('i.supplier_price');

                        if ($supplierPrice === null) {
                            throw new Exception("Нема ціни постачальника для product_id={$pid}");
                        }

                        // отримано
                        $received = (int) DB::table('supplier_delivery_items as i')
                            ->join('supplier_deliveries as d', 'd.id', '=', 'i.delivery_id')
                            ->where('d.status', 'accepted')
                            ->where('i.product_id', $pid)
                            ->sum('i.qty_accepted');

                        // вже продано
                        $sold = (int) DB::table('sales')
                            ->where('product_id', $pid)
                            ->sum('qty');

                        $available = $received - $sold;

                        if ($qty > $available) {
                            throw new Exception("Недостатньо на складі для product_id={$pid}. Доступно: {$available}, вводиш: {$qty}");
                        }

                        DB::table('sales')->insert([
                            'product_id' => $pid,
                            'qty' => $qty,
                            'supplier_price' => $supplierPrice,
                            'sold_at' => $soldAt,
                            'created_by' => $u->id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                });

                return response()->json(['ok' => true]);

            } catch (\Throwable $e) {

                return response()->json([
                    'error' => $e->getMessage()
                ], 422);

            }
        });



    
        Route::get('/sales/summary', function (Request $request) {

            $u = $request->user();
            if (!$u || !in_array($u->role, ['owner', 'accountant'], true)) {
                return response()->json(['error' => 'Forbidden'], 403);
            }

            $from = $request->query('from');
            $to   = $request->query('to');

            if (!$from || !$to) {
                return response()->json(['error' => 'from/to required'], 422);
            }

            // 1) Продажі за період
            $salesTotal = (float) DB::table('sales')
                ->whereBetween(DB::raw("date(COALESCE(sold_at, created_at))"), [$from, $to])
                ->sum(DB::raw('qty * supplier_price'));

            // 2) Отримані кошти менеджером за період
            $paidTotal = (float) DB::table('supplier_cash_transfers')
                ->where('is_received', 1)
                ->where('currency', 'USD')
                ->whereNotNull('received_at')
                ->whereBetween(DB::raw("date(received_at)"), [$from, $to])
                ->sum('amount');

            // 3) Нетто “до сплати”
            $total = max(0, $salesTotal - $paidTotal);

            return response()->json([
                'from' => $from,
                'to' => $to,
                'total' => round($total, 2),          // ✅ фронт хай бере як і брав
                'sales_total' => round($salesTotal, 2),
                'paid_total' => round($paidTotal, 2),
            ]);
        });


        
        


        /*
        |--------------------------
        | CSV preview/import
        |--------------------------
        */
        Route::post('/bank/csv-preview', function (Request $request) {

            if (!$request->hasFile('file')) {
                return response()->json(['error' => 'No file'], 400);
            }

            $file = $request->file('file');
            $content = file_get_contents($file->getRealPath());

            // Укргазбанк = Windows-1251
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1251');

            $lines  = explode("\n", trim($content));
            $header = str_getcsv(array_shift($lines), ';');

            $rows = [];
            foreach ($lines as $line) {
                if (!trim($line)) continue;

                $cols = str_getcsv($line, ';');
                $row = [];

                foreach ($header as $i => $key) {
                    $row[$key] = $cols[$i] ?? null;
                }

                $rows[] = $row;
            }

            return response()->json([
                'columns' => $header,
                'rows' => array_slice($rows, 0, 20),
            ]);
        });

        Route::post('/bank/csv-import', function (Request $request) {

            if (!$request->hasFile('file')) {
                return response()->json(['error' => 'No file'], 400);
            }

            $file = $request->file('file');

            $content = mb_convert_encoding(
                file_get_contents($file->getRealPath()),
                'UTF-8',
                'Windows-1251'
            );

            $lines  = preg_split("/\r\n|\n|\r/", trim($content));
            $header = str_getcsv(array_shift($lines), ';');

            $inserted = 0;
            $skipped  = 0;

            foreach ($lines as $line) {
                if (!trim($line)) continue;

                $cols = str_getcsv($line, ';');
                if (count($cols) < count($header)) continue;

                $row = [];
                foreach ($header as $i => $key) {
                    $row[$key] = $cols[$i] ?? null;
                }

                $hash = sha1(
                    'ukrgasbank|' .
                    ($row['DATA_D'] ?? '') . '|' .
                    ($row['DK'] ?? '') . '|' .
                    ($row['SUM_PD_NOM'] ?? '') . '|' .
                    ($row['PURPOSE'] ?? '') . '|' .
                    ($row['NAME_KOR'] ?? '')
                );

                if (BankTransactionRaw::where('hash', $hash)->exists()) {
                    $skipped++;
                    continue;
                }

                BankTransactionRaw::create([
                    'bank_code'      => 'ukrgasbank',
                    'operation_date' => $row['DATA_D'] ?? null,
                    'dk'             => (string)($row['DK'] ?? null),
                    'amount'         => (float)($row['SUM_PD_NOM'] ?? 0),
                    'currency'       => 'UAH',
                    'counterparty'   => $row['NAME_KOR'] ?? null,
                    'purpose'        => $row['PURPOSE'] ?? null,
                    'raw'            => $row,
                    'hash'           => $hash,
                ]);

                $inserted++;
            }

            return [
                'inserted' => $inserted,
                'skipped'  => $skipped,
            ];
        });

        /*
        |--------------------------
        | Bank accounts
        |--------------------------
        */
        Route::get('/bank/accounts', function () {

            $response = Http::withToken(config('services.ukrgasbank.token'))
                ->get('https://my.ukrgasbank.com/api/v3/accounts');

            if (!$response->ok()) {
                return response()->json([], 500);
            }

            $rows = $response->json()['rows'] ?? [];

            $accounts = collect($rows)
                ->map(function ($a) {

                    $balance = $a['balance']['closingBalance']
                        ?? $a['balance']['balance']
                        ?? 0;

                    return [
                        'id'       => $a['id'],
                        'iban'     => $a['iban'],
                        'name'     => 'ТОВ "СОЛАР ІНЖЕНІРІНГ"',
                        'currency' => $a['currency'],
                        'balance'  => (float)$balance,
                        'bankCode' => 'ukrgasbank_engineering',
                    ];
                })
                ->filter(fn ($a) => abs($a['balance']) > 0.01)
                ->filter(fn ($a) => abs($a['balance']) > 100)
                ->values();

            return response()->json($accounts);
        });

        Route::get('/bank/accounts-sggroup', function () {

            $response = Http::withToken(env('UKRGASBANK_SGGROUP_TOKEN'))
                ->get('https://my.ukrgasbank.com/api/v3/accounts');

            if (!$response->ok()) {
                return response()->json([], 500);
            }

            $rows = $response->json()['rows'] ?? [];

            $accounts = collect($rows)
                ->map(function ($a) {

                    $balance = $a['balance']['closingBalance']
                        ?? $a['balance']['balance']
                        ?? 0;

                    return [
                        'id'       => 'sggroup_' . $a['id'],
                        'iban'     => $a['iban'],
                        'name'     => 'ТОВ "СГ ГРУП"',
                        'currency' => $a['currency'],
                        'balance'  => (float)$balance,
                        'bankCode' => 'ukrgasbank_sggroup',
                    ];
                })
                ->filter(fn ($a) => abs($a['balance']) > 100)
                ->values();

            return response()->json($accounts);
        });

        Route::get('/bank/accounts-solarglass', function () {

            $response = Http::withToken(config('services.ukrgasbank_solarglass.token'))
                ->get('https://my.ukrgasbank.com/api/v3/accounts');

            if (!$response->ok()) {
                return response()->json([], 500);
            }

            $rows = $response->json()['rows'] ?? [];

            $accounts = collect($rows)
                ->filter(fn($a) => ($a['iban'] ?? '') === 'UA413204780000026004924944262')
                ->map(function ($a) {

                    $balance = $a['balance']['closingBalance']
                        ?? $a['balance']['balance']
                        ?? 0;

                    return [
                        'id'       => 'solarglass_' . $a['id'],
                        'iban'     => $a['iban'],
                        'name'     => 'ТОВ "СОЛАР ГЛАСС"',
                        'currency' => $a['currency'],
                        'balance'  => (float)$balance,
                        'bankCode' => 'ukrgasbank_solarglass',
                    ];
                })
                ->values();

            return response()->json($accounts);
        });

        // Privat accounts
        Route::get('/bank/accounts-privat', function () {

            $token = config('services.privatbank.token');
            if (!$token) return response()->json([]);

            $response = Http::withToken($token)
                ->get('https://api.privatbank.ua/p24api/rest_fiz', [
                    'json' => '',
                    'action' => 'balance'
                ]);

            if (!$response->ok()) {
                return response()->json([]);
            }

            $cards = $response->json()['accounts'] ?? [];

            $accounts = collect($cards)
                ->map(function ($c) {
                    return [
                        'id'       => 'privat_' . $c['acc'],
                        'iban'     => null,
                        'name'     => 'ТОВ "СОЛАР ГЛАСС"',
                        'currency' => $c['currency'],
                        'balance'  => (float)$c['balance'],
                        'bankCode' => 'privatbank',
                    ];
                })
                ->filter(fn ($a) => abs($a['balance']) > 1)
                ->values();

            return response()->json($accounts);
        });

        // Monobank accounts
        Route::get('/bank/accounts-monobank', function () {

            $token = env('MONOBANK_TOKEN');
            if (!$token) return response()->json([]);

            $res = Http::withHeaders([
                'X-Token' => $token,
            ])->get('https://api.monobank.ua/personal/client-info');

            if (!$res->ok()) return response()->json([]);

            $mainIban = 'UA253220010000026005310038535';

            $accounts = collect($res->json()['accounts'] ?? [])
                ->filter(fn ($a) => ($a['iban'] ?? null) === $mainIban)
                ->map(function ($a) {
                    return [
                        'id'       => 'mono_' . $a['id'],
                        'iban'     => $a['iban'],
                        'name'     => 'ФОП КОЛІСНИК',
                        'currency' => 'UAH',
                        'balance'  => $a['balance'] / 100,
                        'bankCode' => 'monobank',
                    ];
                })
                ->values();

            return response()->json($accounts);
        });

        /*
        |--------------------------
        | Transactions
        |--------------------------
        */
        Route::get('/bank/transactions', function () {

            $rows = BankTransactionRaw::query()
                ->where('bank_code', 'ukrgasbank')
                ->orderBy('operation_date', 'desc')
                ->orderBy('id', 'desc')
                ->limit(200)
                ->get([
                    'id',
                    'operation_date',
                    'dk',
                    'amount',
                    'currency',
                    'counterparty',
                    'purpose',
                ]);

            return response()->json($rows);
        });

        Route::get('/bank/transactions-sggroup', function (Request $request) {

            $iban = $request->query('iban');
            if (!$iban) return response()->json([], 400);

            $response = Http::withToken(env('UKRGASBANK_SGGROUP_TOKEN'))
                ->get('https://my.ukrgasbank.com/api/v1/ugb/external/transaction-history');

            if (!$response->ok()) return response()->json([], 500);

            $rows = $response->json()['rows'] ?? [];

            $tx = collect($rows)
                ->filter(fn ($r) =>
                    ($r['DB_IBAN'] ?? null) === $iban ||
                    ($r['CR_IBAN'] ?? null) === $iban
                )
                ->map(function ($r) {
                    $isIncome = ($r['DK'] ?? null) == 1;

                    return [
                        'date'    => $r['DATA_D'] ?? $r['DATA_VYP'] ?? null,
                        'amount'  => (float)($r['SUM_PD_NOM'] ?? 0) * ($isIncome ? 1 : -1),
                        'comment' => trim($r['PURPOSE'] ?? ''),
                        'counterparty' => $isIncome
                            ? ($r['NAME_F'] ?? $r['NAME_KOR'] ?? '')
                            : ($r['NAME_KOR'] ?? $r['NAME_F'] ?? ''),
                    ];
                })
                ->sortByDesc('date')
                ->values();

            return response()->json($tx);
        });

        Route::get('/bank/transactions-engineering', function (Request $request) {

            $iban = $request->query('iban');
            if (!$iban) return response()->json([], 400);

            $response = Http::withToken(config('services.ukrgasbank.token'))
                ->get('https://my.ukrgasbank.com/api/v1/ugb/external/transaction-history');

            if (!$response->ok()) return response()->json([], 500);

            $rows = $response->json()['rows'] ?? [];

            $tx = collect($rows)
                ->filter(fn ($r) =>
                    ($r['DB_IBAN'] ?? null) === $iban ||
                    ($r['CR_IBAN'] ?? null) === $iban
                )
                ->map(function ($r) {
                    $isIncome = ($r['DK'] ?? null) == 1;

                    return [
                        'date'    => $r['DATA_D'] ?? $r['DATA_VYP'] ?? null,
                        'amount'  => (float)($r['SUM_PD_NOM'] ?? 0) * ($isIncome ? 1 : -1),
                        'comment' => trim($r['PURPOSE'] ?? ''),
                        'counterparty' => $isIncome
                            ? ($r['NAME_F'] ?? $r['NAME_KOR'] ?? '')
                            : ($r['NAME_KOR'] ?? $r['NAME_F'] ?? ''),
                    ];
                })
                ->sortByDesc('date')
                ->values();

            return response()->json($tx);
        });

        Route::get('/bank/transactions-solarglass', function (Request $request) {

            $iban = $request->query('iban');
            if (!$iban) return response()->json([], 400);

            $response = Http::withToken(config('services.ukrgasbank_solarglass.token'))
                ->get('https://my.ukrgasbank.com/api/v1/ugb/external/transaction-history');

            if (!$response->ok()) return response()->json([], 500);

            $rows = $response->json()['rows'] ?? [];

            $tx = collect($rows)
                ->filter(fn ($r) =>
                    ($r['DB_IBAN'] ?? null) === $iban ||
                    ($r['CR_IBAN'] ?? null) === $iban
                )
                ->map(function ($r) {
                    $isIncome = ($r['DK'] ?? null) == 1;

                    return [
                        'date'    => $r['DATA_D'] ?? $r['DATA_VYP'] ?? null,
                        'amount'  => (float)($r['SUM_PD_NOM'] ?? 0) * ($isIncome ? 1 : -1),
                        'comment' => trim($r['PURPOSE'] ?? ''),
                        'counterparty' => $isIncome
                            ? ($r['NAME_F'] ?? $r['NAME_KOR'] ?? '')
                            : ($r['NAME_KOR'] ?? $r['NAME_F'] ?? ''),
                    ];
                })
                ->sortByDesc('date')
                ->values();

            return response()->json($tx);
        });

        Route::get('/bank/transactions-privat', function (Request $request) {

            $id = $request->query('id');
            if (!$id) return response()->json([]);

            $token = config('services.privatbank.token');

            $response = Http::withToken($token)
                ->get('https://api.privatbank.ua/p24api/rest_fiz', [
                    'json' => '',
                    'action' => 'transactions',
                    'card' => $id,
                    'date_from' => now()->subDays(30)->format('d.m.Y'),
                    'date_to' => now()->format('d.m.Y'),
                ]);

            if (!$response->ok()) return response()->json([]);

            $rows = $response->json()['transactions'] ?? [];

            $tx = collect($rows)->map(function ($r) {
                return [
                    'date'    => \Carbon\Carbon::createFromFormat('d.m.Y H:i:s', $r['date'])->format('Y-m-d'),
                    'amount'  => (float)$r['amount'],
                    'comment' => $r['description'] ?? '',
                ];
            })->sortByDesc('date')->values();

            return response()->json($tx);
        });

        Route::get('/bank/transactions-monobank', function (Request $request) {

            $accountId = $request->query('id');
            if (!$accountId) return response()->json([]);

            $token = env('MONOBANK_TOKEN');

            $from = now()->subDays(30)->timestamp;
            $to   = now()->timestamp;

            $res = Http::withHeaders([
                'X-Token' => $token,
            ])->get("https://api.monobank.ua/personal/statement/{$accountId}/{$from}/{$to}");

            if (!$res->ok()) return response()->json([]);

            $rows = collect($res->json())
                ->map(function ($r) {
                    return [
                        'date'    => date('Y-m-d', $r['time']),
                        'amount'  => $r['amount'] / 100,
                        'comment' => $r['description'] ?? '',
                        'counterparty' => $r['mcc'] ?? '',
                    ];
                })
                ->sortByDesc('date')
                ->values();

            return response()->json($rows);
        });

        // balance calc from raw
        Route::get('/bank/balance', function () {
            $rows = BankTransactionRaw::where('bank_code', 'ukrgasbank')->get();

            $balance = 0;
            foreach ($rows as $r) {
                if ((string)$r->dk === '1') {
                    $balance += (float)$r->amount;
                } else {
                    $balance -= (float)$r->amount;
                }
            }

            return [
                'currency' => 'UAH',
                'balance'  => round($balance, 2),
                'count'    => $rows->count(),
            ];
        });

    });


    /*
    |--------------------------------------------------------------------------
    | Debug routes (краще захистити додатково, але лишаю як є)
    |--------------------------------------------------------------------------
    */
    Route::prefix('debug')->group(function () {

        Route::get('/ukrgasbank', function () {

            $token = config('services.ukrgasbank.token');
            if (!$token) return '❌ Token not found';

            $response = Http::withToken($token)
                ->get('https://my.ukrgasbank.com/api/v3/accounts');

            return [
                'status' => $response->status(),
                'body'   => $response->json(),
            ];
        });

        Route::get('/ukrgasbank/transactions', function () {

            $token = config('services.ukrgasbank.token');
            if (!$token) return '❌ Token not found';

            $response = Http::withToken($token)
                ->get('https://my.ukrgasbank.com/api/v1/ugb/external/transaction-history', [
                    'dateFrom' => now()->subDays(7)->format('Y-m-d'),
                    'dateTo'   => now()->format('Y-m-d'),
                ]);

            return [
                'status' => $response->status(),
                'body'   => $response->json(),
            ];
        });

        Route::get('/ukrgasbank/import', function () {

            $token = config('services.ukrgasbank.token');
            if (!$token) return '❌ Token not found';

            $response = Http::withToken($token)
                ->get('https://my.ukrgasbank.com/api/v1/ugb/external/transaction-history', [
                    'dateFrom' => now()->subDays(7)->format('Y-m-d'),
                    'dateTo'   => now()->format('Y-m-d'),
                ]);

            if (!$response->ok()) return ['status' => $response->status()];

            $rows = $response->json()['rows'] ?? [];
            $inserted = 0;
            $skipped  = 0;

            foreach ($rows as $r) {

                $hash = sha1(
                    'ukrgasbank|' .
                    ($r['operDate'] ?? '') . '|' .
                    ($r['dk'] ?? '') . '|' .
                    ($r['amount'] ?? '') . '|' .
                    ($r['purpose'] ?? '') . '|' .
                    ($r['counterparty'] ?? '')
                );

                if (BankTransactionRaw::where('hash', $hash)->exists()) {
                    $skipped++;
                    continue;
                }

                BankTransactionRaw::create([
                    'bank_code'      => 'ukrgasbank',
                    'account_iban'   => $r['iban'] ?? null,
                    'external_id'    => $r['id'] ?? null,
                    'hash'           => $hash,
                    'operation_date' => $r['operDate'] ?? null,
                    'dk'             => $r['dk'] ?? null,
                    'amount'         => $r['amount'] ?? null,
                    'currency'       => $r['currency'] ?? null,
                    'counterparty'   => $r['counterparty'] ?? null,
                    'purpose'        => $r['purpose'] ?? null,
                    'raw'            => $r,
                ]);

                $inserted++;
            }

            return [
                'total'    => count($rows),
                'inserted' => $inserted,
                'skipped'  => $skipped,
            ];
        });

    });

    

    Route::get('/stock', function () {
        return view('stock.index');
    });

    Route::get('/stock/supplier-cash', function () {
        return view('stock.supplier-cash');
    });


    Route::get('/deliveries/create', function () {
        return view('deliveries.create');
    });

    Route::get('/deliveries/{id}', function ($id) {
        return view('deliveries.show', ['id' => $id]);
    });


    Route::get('/deliveries', function () {
        return view('deliveries.index');
    });

    Route::get('/stock/sales', function (Request $request) {
        $u = $request->user();

        if (!$u || !in_array($u->role, ['owner', 'accountant'], true)) {
            abort(403);
        }

        return view('stock.sales');
    });

    Route::get('/finance', function () {
        return view('finance.finance');
    });

    Route::middleware(['auth'])->prefix('api/fem')->group(function () {
        Route::get('/containers', [FemDebtController::class, 'index']);                 // manager/owner/accountant
        Route::post('/containers', [FemDebtController::class, 'storeContainer']);      // manager only (контролер вже перевіряє)
        Route::patch('/containers/{id}', [FemDebtController::class, 'updateContainer']); // manager only
        Route::post('/containers/{id}/payments', [FemDebtController::class, 'storePayment']); // owner/accountant only
        Route::post('/payments/{paymentId}/received', [FemDebtController::class, 'receivePayment']);

    });


        // ======================
        // Діаграмма боргів по санфіксу
        // ======================

        Route::middleware(['auth'])->get('/api/debt-chart', function () {

            // ---------- 1) Інверторне: борг загальний ----------
            $salesTotal = (float) DB::table('sales')->sum(DB::raw('qty * supplier_price'));

            $cashQ = DB::table('supplier_cash_transfers')->where('is_received', 1);

            // якщо є колонка currency — фільтруємо USD
            if (\Illuminate\Support\Facades\Schema::hasColumn('supplier_cash_transfers', 'currency')) {
                $cashQ->where('currency', 'USD');
            }

            $paidTotal = (float) $cashQ->sum('amount');

            $inverterDebtTotal = max(0, $salesTotal - $paidTotal);

            // ---------- 2) Інверторне: борг по категоріях ----------
            // ✅ беремо НАЗВИ категорій з таблиці product_categories, щоб не було "Категорія #4"
            if (\Illuminate\Support\Facades\Schema::hasTable('product_categories')
                && \Illuminate\Support\Facades\Schema::hasColumn('products', 'category_id')) {

                // всі існуючі категорії (навіть якщо зараз 0 продажів)
                $salesByCat = DB::table('product_categories as pc')
                    ->leftJoin('products as p', 'p.category_id', '=', 'pc.id')
                    ->leftJoin('sales as s', 's.product_id', '=', 'p.id')
                    ->groupBy('pc.id', 'pc.name')
                    ->select(
                        'pc.name as key',
                        DB::raw("COALESCE(SUM(s.qty * s.supplier_price),0) as sales_total")
                    )
                    ->get()
                    ->map(fn($r) => [
                        'label' => trim($r->key ?: 'Без категорії'),
                        'sales_total' => (float) $r->sales_total
                    ]);

                // окремо: товари без category_id (якщо такі є)
                $uncatSales = (float) DB::table('sales as s')
                    ->join('products as p', 'p.id', '=', 's.product_id')
                    ->whereNull('p.category_id')
                    ->sum(DB::raw('s.qty * s.supplier_price'));

                if ($uncatSales > 0) {
                    $salesByCat = $salesByCat->push([
                        'label' => 'Без категорії',
                        'sales_total' => $uncatSales
                    ]);
                }

            } elseif (\Illuminate\Support\Facades\Schema::hasColumn('products', 'category_name')) {

                // fallback: якщо в products прямо зберігається category_name
                $salesByCat = DB::table('sales as s')
                    ->join('products as p', 'p.id', '=', 's.product_id')
                    ->groupBy('p.category_name')
                    ->select('p.category_name as key', DB::raw("SUM(s.qty * s.supplier_price) as sales_total"))
                    ->get()
                    ->map(fn($r) => ['label' => trim($r->key ?: 'Без категорії'), 'sales_total' => (float)$r->sales_total]);

            } else {

                // взагалі немає нормальних категорій
                $salesByCat = collect([
                    ['label' => 'Без категорії', 'sales_total' => $salesTotal]
                ]);
            }


            $sumCatSales = (float) $salesByCat->sum('sales_total');

            $inverterByCategory = $salesByCat->map(function ($r) use ($paidTotal, $sumCatSales) {
                $sales = (float) $r['sales_total'];
                $share = ($sumCatSales > 0) ? ($sales / $sumCatSales) : 0;
                $paidAllocated = $paidTotal * $share;
                $debt = max(0, $sales - $paidAllocated);

                return [
                    'category' => $r['label'],
                    'debt' => round($debt, 2),
                ];
            })->sortByDesc('debt')->values();






            // ---------- 3) ФЕМ: борг загальний + по виробниках (усе в PHP, без SUBSTRING_INDEX/GREATEST) ----------
            $containers = DB::table('fem_containers as c')
                ->leftJoin('fem_container_payments as p', 'p.fem_container_id', '=', 'c.id')
                ->groupBy('c.id', 'c.name', 'c.amount')
                ->select(
                    'c.id',
                    'c.name',
                    'c.amount',
                    DB::raw('COALESCE(SUM(p.amount),0) as paid_sum')
                )
                ->orderByDesc('c.id')
                ->get();

            $femTotalDebt = 0.0;
            $byBrand = [];

            foreach ($containers as $c) {
                $amount = (float) $c->amount;
                $paid   = (float) $c->paid_sum;
                $balance = $amount - $paid;

                $pos = max(0, $balance);
                $femTotalDebt += $pos;

                $name = trim((string)($c->name ?? ''));
                $brand = $name ? preg_split('/\s+/', $name)[0] : 'Невідомо';

                if (!isset($byBrand[$brand])) $byBrand[$brand] = 0.0;
                $byBrand[$brand] += $pos;
            }

            $femByBrand = collect($byBrand)
                ->map(fn($v,$k) => ['brand' => $k, 'debt' => round((float)$v, 2)])
                ->sortByDesc('debt')
                ->values();

            $totalDebt = $inverterDebtTotal + $femTotalDebt;

            return response()->json([
                'total_debt' => round($totalDebt, 2),

                'inverter_debt' => round($inverterDebtTotal, 2),
                'inverter_by_category' => $inverterByCategory,

                'fem_debt' => round($femTotalDebt, 2),
                'fem_by_brand' => $femByBrand,
            ]);
            
        });







    /*
    |--------------------------------------------------------------------------
    | Reclamations (як у тебе, але вже під глобальним middleware)
    |--------------------------------------------------------------------------
    */
    Route::prefix('reclamations')
        ->name('reclamations.')
        ->middleware(['reclamations.access'])
        ->group(function () {

            Route::get('/', [ReclamationController::class, 'index'])->name('index');
            Route::get('/new', [ReclamationController::class, 'new'])->name('new');
            Route::get('/create', [ReclamationController::class, 'create'])->name('create');
            Route::post('/', [ReclamationController::class, 'store'])->name('store');
            Route::get('/{reclamation}', [ReclamationController::class, 'show'])->name('show');
            Route::delete('/{reclamation}', [ReclamationController::class, 'destroy'])
                ->name('destroy');

            Route::post('/{reclamation}/steps/{stepKey}', [ReclamationController::class, 'saveStep'])->name('steps.save');
            Route::post('/{reclamation}/upload', [ReclamationController::class, 'upload'])->name('upload');
        });


    // history поза prefix у тебе було, можна лишити так (або всередину group)
    Route::get('/reclamations/{reclamation}/history', [ReclamationController::class, 'history'])
        ->name('reclamations.history')
        ->middleware(['reclamations.access']);


});

require __DIR__ . '/auth.php';
