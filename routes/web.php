<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AI\AIChatController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\ReclamationController;
use App\Http\Controllers\FemDebtController;
use App\Http\Controllers\CashTransferController;
use App\Http\Controllers\SalaryRuleController;
use App\Http\Controllers\ServiceRequestController;
use App\Http\Controllers\StockController;
use App\Models\AutomationLog;
use App\Services\AutomationService;


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

    // Solar Glass stock page
    Route::view('/solar-glass', 'solar-glass')->name('solar-glass');

    Route::get('/api/solar-glass/stock', function (Request $request) {
        $query = DB::table('solarglass_stock')
            ->select('item_code', 'item_name', 'qty', 'updated_at')
            ->where('qty', '>', 0)
            ->where(function ($q) {
                $q->where('item_name', 'like', 'Фотомодул%')
                  ->orWhere('item_name', 'like', 'Інвертор%')
                  ->orWhere('item_name', 'like', 'інвертор%')
                  ->orWhere('item_name', 'like', 'АКБ%');
            });

        if ($search = trim((string) $request->query('q', ''))) {
            $query->where('item_name', 'like', '%' . $search . '%');
        }

        $category = trim((string) $request->query('category', ''));
        if ($category === 'panels') {
            $query->where('item_name', 'like', 'Фотомодул%');
        } elseif ($category === 'inverters') {
            $query->where(function ($q) {
                $q->where('item_name', 'like', 'Інвертор%')
                  ->orWhere('item_name', 'like', 'інвертор%');
            });
        } elseif ($category === 'batteries') {
            $query->where('item_name', 'like', 'АКБ%');
        }

        return response()->json($query->orderBy('item_name')->get());
    });


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
    Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/users/manage', [UserManagementController::class, 'index'])
        ->middleware('only.owner')
        ->name('users.manage');
    Route::post('/users/manage', [UserManagementController::class, 'store'])
        ->middleware('only.owner')
        ->name('users.store');
    Route::patch('/users/manage/{user}', [UserManagementController::class, 'update'])
        ->middleware('only.owner')
        ->name('users.update');
    Route::delete('/users/manage/{user}', [UserManagementController::class, 'destroy'])
        ->middleware('only.owner')
        ->name('users.destroy');
    Route::get('/salary/settings', [SalaryRuleController::class, 'settings'])
        ->middleware('only.owner')
        ->name('salary.settings');
    Route::view('/salary/managers', 'salary.managers.index')
        ->middleware('only.owner')
        ->name('salary.managers');
    Route::view('/salary/fixed/show', 'salary.fixed.show')
        ->middleware('only.owner')
        ->name('salary.fixed.show');

    // AI Financial Assistant (owner only)
    Route::view('/ai', 'ai.chat')
        ->middleware('only.owner')
        ->name('ai.chat');

    Route::post('/api/ai/chat', [AIChatController::class, 'chat'])
        ->middleware('only.owner');

    Route::get('/analytics', function () {
        $now   = now();
        $month = $now->month;
        $year  = $now->year;

        // --- Баланси по валютах (з проведень) ---
        $balances = DB::table('entries as e')
            ->join('wallets as w', 'w.id', '=', 'e.wallet_id')
            ->where('e.entry_type', '!=', 'reversal')
            ->select('w.currency', DB::raw("SUM(CASE WHEN e.entry_type='income' THEN e.amount ELSE -e.amount END) as balance"))
            ->groupBy('w.currency')
            ->orderBy('w.currency')
            ->get()
            ->keyBy('currency');

        // --- Доходи/витрати поточного місяця ---
        $thisMonth = DB::table('entries')
            ->where('entry_type', '!=', 'reversal')
            ->whereYear('posting_date', $year)
            ->whereMonth('posting_date', $month)
            ->select('entry_type', DB::raw('SUM(amount) as total'))
            ->groupBy('entry_type')
            ->get()
            ->keyBy('entry_type');

        // --- Доходи/витрати по місяцях (останні 6) ---
        $monthly = DB::table('entries')
            ->where('entry_type', '!=', 'reversal')
            ->select(DB::raw("strftime('%Y-%m', posting_date) as month"), 'entry_type', DB::raw('SUM(amount) as total'))
            ->groupBy('month', 'entry_type')
            ->orderBy('month')
            ->get()
            ->groupBy('month');

        $months = [];
        foreach ($monthly as $m => $rows) {
            $months[$m] = [
                'income'  => round($rows->where('entry_type', 'income')->sum('total')),
                'expense' => round($rows->where('entry_type', 'expense')->sum('total')),
            ];
        }
        $months = array_slice($months, -6, 6, true);

        // --- Проекти по воронці ---
        $stageLabels = [
            38556547 => 'Частково оплатив',
            69586234 => 'Комплектація',
            38556550 => 'Очікування доставки',
            69593822 => 'Заплановане будівництво',
            69593826 => 'Монтаж',
            69593830 => 'Електрична частина',
            69593834 => 'Здача проекту',
        ];
        $stageCounts = DB::table('amocrm_deal_map')
            ->whereNotNull('amo_status_id')
            ->groupBy('amo_status_id')
            ->select('amo_status_id', DB::raw('count(*) as cnt'))
            ->get()
            ->keyBy('amo_status_id');

        $stages = [];
        foreach (array_reverse($stageLabels, true) as $id => $label) {
            $stages[] = [
                'label' => $label,
                'count' => $stageCounts[$id]->cnt ?? 0,
            ];
        }

        // --- Очікують підтвердження переказів ---
        $pendingTransfers = DB::table('cash_transfers')->where('status', 'pending')->count();

        // --- Обладнання: потрібно по проектах vs на складі ---
        $activeStageIds = [38556547, 69586234, 38556550, 69593822, 69593826, 69593830, 69593834];

        $equipTotals = DB::table('amocrm_deal_map as m')
            ->join('sales_projects as p', 'p.id', '=', 'm.wallet_project_id')
            ->whereIn('m.amo_status_id', $activeStageIds)
            ->select(
                DB::raw('SUM(COALESCE(p.panel_qty, 0)) as panels_needed'),
                DB::raw('SUM(COALESCE(p.battery_qty, 0)) as batteries_needed'),
                DB::raw("COUNT(CASE WHEN p.inverter IS NOT NULL AND p.inverter != '' AND p.inverter != '-' THEN 1 END) as inverters_needed")
            )->first();

        $equipStock = [
            'panels'    => (int) DB::table('solarglass_stock')->where('item_name', 'like', 'Фотомодул%')->sum('qty'),
            'batteries' => (int) DB::table('solarglass_stock')->where('item_name', 'like', 'АКБ%')->sum('qty'),
            'inverters' => (int) DB::table('solarglass_stock')
                ->where(function ($q) {
                    $q->where('item_name', 'like', 'Інвертор%')
                      ->orWhere('item_name', 'like', 'інвертор%');
                })->sum('qty'),
        ];

        $equipBalance = [
            ['label' => 'Фотомодулі', 'needed' => (int)$equipTotals->panels_needed,    'stock' => $equipStock['panels']],
            ['label' => 'АКБ',        'needed' => (int)$equipTotals->batteries_needed, 'stock' => $equipStock['batteries']],
            ['label' => 'Інвертори',  'needed' => (int)$equipTotals->inverters_needed, 'stock' => $equipStock['inverters']],
        ];

        return view('analytics.index', compact(
            'balances', 'thisMonth', 'months', 'stages', 'pendingTransfers', 'month', 'year',
            'equipBalance'
        ));
    })->middleware('only.owner')->name('analytics');
    Route::get('/salary/foreman', function () {
        $user = auth()->user();
        if (!$user || $user->role !== 'worker' || $user->position !== 'foreman') {
            abort(403);
        }

        return view('salary.foreman.show');
    })->name('salary.foreman.show');
    Route::view('/salary/my', 'salary.my')
        ->name('salary.my');
    Route::get('/projects/my-installation', function () {
        $user = auth()->user();
        if (
            !$user
            || $user->role !== 'worker'
            || !in_array($user->actor, ['kryzhanovskyi', 'kukuiaka', 'shevchenko'], true)
        ) {
            abort(403);
        }

        return view('projects.installers');
    })->name('projects.my-installation');
    Route::get('/projects/my-electrician', function () {
        $user = auth()->user();
        if (!$user || $user->role !== 'worker' || $user->position !== 'electrician') {
            abort(403);
        }

        return view('projects.electricians');
    })->name('projects.my-electrician');


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

        

        Route::post('/cash-transfers', [\App\Http\Controllers\CashTransferController::class, 'store']);

        Route::post('/cash-transfers/{id}/accept', [\App\Http\Controllers\CashTransferController::class, 'accept']);

        Route::post('/sales-projects', [\App\Http\Controllers\SalesProjectController::class, 'store']);

        Route::get('/sales-projects', [\App\Http\Controllers\SalesProjectController::class, 'index']);

        Route::post('/sales-projects/{id}/advance', [\App\Http\Controllers\SalesProjectController::class, 'addAdvance']);
        Route::post('/sales-projects/{id}/lead-manager', [\App\Http\Controllers\SalesProjectController::class, 'updateLeadManager']);
        Route::post('/sales-projects/{id}/construction', [\App\Http\Controllers\SalesProjectController::class, 'updateConstruction']);
        Route::post('/sales-projects/{id}/close', [\App\Http\Controllers\SalesProjectController::class, 'closeProject']);
        Route::get('/sales-projects/{id}/history', [\App\Http\Controllers\SalesProjectController::class, 'projectHistory']);
        Route::get('/construction-staff-options', [\App\Http\Controllers\SalesProjectController::class, 'constructionStaffOptions']);
        Route::post('/construction-staff-options', [\App\Http\Controllers\SalesProjectController::class, 'addConstructionStaffOption']);
        Route::delete('/construction-staff-options/{id}', [\App\Http\Controllers\SalesProjectController::class, 'deleteConstructionStaffOption']);
        Route::get('/salary-rules', [SalaryRuleController::class, 'index']);
        Route::get('/salary-rules/settings-data', [SalaryRuleController::class, 'settingsData'])->middleware('only.owner');
        Route::post('/salary-rules', [SalaryRuleController::class, 'upsert'])->middleware('only.owner');
        Route::get('/salary/fixed-employee', [SalaryRuleController::class, 'fixedEmployeeData'])->middleware('only.owner');
        Route::post('/salary/fixed-employee/penalties', [SalaryRuleController::class, 'saveFixedEmployeePenalties'])->middleware('only.owner');
        Route::get('/salary/foreman/my', [SalaryRuleController::class, 'myForemanFixedSalaryData']);
        Route::get('/salary/my', [SalaryRuleController::class, 'mySalaryData']);
        Route::get('/salary/managers-data', [SalaryRuleController::class, 'managerPayoutData'])->middleware('only.owner');
        Route::get('/service-requests', [ServiceRequestController::class, 'index']);
        Route::get('/my-service-requests', [ServiceRequestController::class, 'myIndex']);
        Route::post('/service-requests', [ServiceRequestController::class, 'store']);
        Route::delete('/service-requests/{serviceRequest}', [ServiceRequestController::class, 'destroy']);
        
        Route::post('/send-project-money', [\App\Http\Controllers\CashTransferController::class, 'sendProjectMoney']);

        Route::post('/sales-projects/{id}/target-owner', [\App\Http\Controllers\SalesProjectController::class, 'setTargetOwner']);

        Route::post('/sales-projects/{id}/target-owner-cancel', [\App\Http\Controllers\SalesProjectController::class, 'cancelTargetOwner']);

        Route::put('/cash-transfers/{id}', [CashTransferController::class, 'update']);

        

        Route::get('/stock', function (Request $request) {
            

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





            // ====== BALANCE CHECK ======

            $soldCostTotal = (float) DB::table('sales')
                ->sum(DB::raw('qty * supplier_price'));

            $paidTotal = (float) DB::table('supplier_cash_transfers')
                ->where('is_received', 1)
                ->sum('amount');

           // Борг постачальнику (за весь час)
            $supplierDebt = max(0, $soldCostTotal - $paidTotal);

            // Баланс ок (логічна перевірка)
            $balanceOk = abs($soldCostTotal - ($paidTotal + $supplierDebt)) < 0.01;


            return response()->json([
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

    Route::get('/stock/sales-reports', [StockController::class, 'salesReports'])
        ->middleware('only.owner.or.sunfix.manager')
        ->name('stock.sales-reports');

    Route::get('/finance', function () {
        $u = auth()->user();
        if (!$u || !in_array($u->role, ['owner', 'ntv'], true)) abort(403);

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
        

    Route::middleware(['auth'])->get('/projects', function () {
        return view('projects.project');
    });

    Route::middleware(['auth', 'only.owner'])->get('/equipment-orders', function () {
        return view('projects.equipment-orders');
    });

    // Norm rules (battery / panel / inverter) — єдина сторінка
    Route::middleware(['auth', 'only.owner'])->get('/norm-rules', function () {
        return view('projects.norm-rules');
    });
    Route::middleware(['auth', 'only.owner'])->get('/api/norm-rules', function (Request $request) {
        $type = trim((string) $request->query('type', ''));
        $q = DB::table('battery_norm_rules')->orderByDesc('sort_order')->orderBy('id');
        if ($type !== '') $q->where('type', $type);
        return response()->json($q->get());
    });
    Route::middleware(['auth', 'only.owner'])->post('/api/norm-rules', function (Request $request) {
        $match   = trim((string) $request->input('match_text', ''));
        $output  = trim((string) $request->input('output_name', ''));
        $order   = (int) $request->input('sort_order', 0);
        $isRegex = (bool) $request->input('is_regex', false);
        $type    = in_array($request->input('type'), ['battery','panel','inverter']) ? $request->input('type') : 'battery';
        if ($match === '' || $output === '') return response()->json(['error' => 'Обидва поля обовʼязкові'], 422);
        $id = DB::table('battery_norm_rules')->insertGetId([
            'type'        => $type,
            'match_text'  => $match,
            'output_name' => $output,
            'sort_order'  => $order,
            'is_regex'    => $isRegex,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
        return response()->json(DB::table('battery_norm_rules')->find($id));
    });
    Route::middleware(['auth', 'only.owner'])->delete('/api/norm-rules/{id}', function (int $id) {
        DB::table('battery_norm_rules')->where('id', $id)->delete();
        return response()->json(['ok' => true]);
    });

    Route::middleware(['auth', 'only.owner'])->get('/api/equipment-orders', function () {
        $kompl = 69586234;

        $projects = \Illuminate\Support\Facades\DB::table('sales_projects as sp')
            ->join('amocrm_deal_map as adm', 'adm.wallet_project_id', '=', 'sp.id')
            ->where('adm.amo_status_id', $kompl)
            ->where('sp.status', '!=', 'completed')
            ->select([
                'sp.id',
                'sp.client_name',
                'sp.inverter',
                'sp.bms',
                'sp.battery_name',
                'sp.battery_qty',
                'sp.panel_name',
                'sp.panel_qty',
                'sp.total_amount',
                'sp.currency',
            ])
            ->orderBy('sp.client_name')
            ->get();

        // Нормалізація назви панелі: "Trina Solar 625 W, 84 шт" → "Trina 625"
        // DB panel rules завантажуються нижче і застосовуються як override після brand+watt
        $normalizeEquipName = function(string $raw) use (&$normRulesPanel, &$applyRules): string {
            // DB override — перевіряємо першим
            if (isset($normRulesPanel) && isset($applyRules)) {
                $override = $applyRules(trim($raw), $normRulesPanel);
                if ($override !== null) return $override;
            }
            if (preg_match('/trina|тріна|трина|tsm\d{3}/iu', $raw)) {
                $brand = 'Trina';
            } elseif (preg_match('/longi|лонг[іиій]|лонджі/iu', $raw)) {
                $brand = 'Longi';
            } elseif (preg_match('/jinko|джінко|джинко|tiger\s*neo/iu', $raw)) {
                $brand = 'Jinko';
            } elseif (preg_match('/canadian/iu', $raw)) {
                $brand = 'Canadian';
            } else {
                $brand = null;
            }
            $watts = null;
            if (preg_match_all('/(?<!\d)([4-7]\d{2})(?!\d)/', $raw, $m)) {
                foreach ($m[1] as $num) {
                    if ((int)$num >= 400 && (int)$num <= 700) { $watts = (int)$num; break; }
                }
            }
            if (preg_match('/(?<![a-zA-Z])bifacial(?![a-zA-Z])/i', $raw))           $suffix = ' Bifacial';
            elseif (preg_match('/(?<![a-zA-Z])(bdv|bdf|bd|bif)(?![a-zA-Z])/i', $raw)) $suffix = ' BD';
            else                                                                         $suffix = '';
            if ($brand && $watts) return "$brand $watts$suffix";
            if ($watts)           return "{$watts}W$suffix";
            return preg_replace('/[\s,\-\/]+\d+\s*шт.*$/ui', '', trim($raw)) ?: trim($raw);
        };

        // Завантажуємо всі правила нормалізації з БД
        $allNormRules = DB::table('battery_norm_rules')->orderByDesc('sort_order')->orderBy('id')->get()->groupBy('type');
        $normRulesBattery  = $allNormRules->get('battery',  collect());
        $normRulesPanel    = $allNormRules->get('panel',    collect());
        $normRulesInverter = $allNormRules->get('inverter', collect());

        $applyRules = function(string $s, $rules): ?string {
            foreach ($rules as $rule) {
                $matched = $rule->is_regex
                    ? preg_match('/' . $rule->match_text . '/iu', $s)
                    : stripos($s, $rule->match_text) !== false;
                if ($matched) return $rule->output_name;
            }
            return null;
        };

        // Нормалізація для батарей: відрізаємо кількість + правила з БД
        $normalizeBatteryName = function(string $raw) use ($normRulesBattery, $applyRules): string {
            $s = preg_replace('/[\s,\-\/]+\d+\s*шт[^а-яіїє]*$/ui', '', trim($raw));
            $s = preg_replace('/[,\/]\s*\d+\s*$/', '', $s);
            $s = trim($s) ?: trim($raw);
            return $applyRules($s, $normRulesBattery) ?? $s;
        };

        // Нормалізація інверторів: DB rules → SolaX auto-detect → raw trim
        $normalizeInverterName = function(string $raw) use ($normRulesInverter, $applyRules): string {
            $s = trim($raw);

            // DB rules — найвищий пріоритет
            $fromDb = $applyRules($s, $normRulesInverter);
            if ($fromDb !== null) return $fromDb;

            // SolaX auto-normalization
            // Тригер: "solax", "Інвертор X..." або паттерн X[1-9]-[A-Z]+
            $isSolax = preg_match('/solax/i', $s)
                || preg_match('/(?:інвертор|інвектор)\s+X[1-9]/iu', $s)
                || preg_match('/\bX[1-9]-(?:HYB|NEO|LITE|MIC|FIT|BOOST)/i', $s);

            if ($isSolax) {
                // Серія X: X1, X3 тощо
                $series = null;
                if (preg_match('/\bX([1-9])\b/i', $s, $sm)) {
                    $series = 'X' . $sm[1];
                }

                // Тип моделі → завжди "Hybrid" для HYB/NEO/LITE/Hybrid
                $type = 'Hybrid'; // всі відомі варіанти = Hybrid

                // Потужність: 6.0, 8.0, 12K, 10kw, 6k тощо
                $power = null;
                // Спочатку десяткова: 6.0, 8.0 (без K)
                if (preg_match('/\b(\d+)\.(\d+)\s*(?:-|k(?:w)?)?\b/i', $s, $pm)) {
                    $kw = (int)$pm[1]; // 6.0 → 6, 8.0 → 8
                } elseif (preg_match('/\b(\d+)\s*k(?:w)?\b/i', $s, $pm)) {
                    $kw = (int)$pm[1];
                } else {
                    $kw = null;
                }
                if ($kw !== null) {
                    $allowed = [6, 8, 10, 12, 15, 20, 30];
                    $closest = $allowed[0];
                    foreach ($allowed as $v) {
                        if (abs($v - $kw) < abs($closest - $kw)) $closest = $v;
                    }
                    $power = $closest . 'K';
                }

                // Напруга: LV або HV
                $voltage = null;
                if (preg_match('/\b(LV|HV)\b/i', $s, $vm)) {
                    $voltage = strtoupper($vm[1]);
                }

                $parts = array_filter(['Solax', $series ? $series . ' ' . $type : null, $power, $voltage]);
                if (count($parts) > 1) return implode(' ', $parts);
            }

            return $s;
        };

        // Зведення по обладнанню (кількість однакових позицій)
        $inverterSummary = [];
        $bmsSummary = [];
        $batterySummary = [];
        $panelSummary = [];

        $isEmpty = fn($v) => empty($v) || trim($v) === '-' || trim($v) === '—';

        foreach ($projects as $p) {
            if (!$isEmpty($p->inverter)) {
                $key = $normalizeInverterName(trim($p->inverter));
                $inverterSummary[$key] = ($inverterSummary[$key] ?? 0) + 1;
            }
            if (!$isEmpty($p->bms)) {
                $key = trim($p->bms);
                $bmsSummary[$key] = ($bmsSummary[$key] ?? 0) + 1;
            }
            if (!$isEmpty($p->battery_name)) {
                $key = $normalizeBatteryName(trim($p->battery_name));
                $qty = max(1, (int)($p->battery_qty ?? 1));
                $batterySummary[$key] = ($batterySummary[$key] ?? 0) + $qty;
            }
            if (!$isEmpty($p->panel_name)) {
                $key = $normalizeEquipName(trim($p->panel_name));
                $qty = max(1, (int)($p->panel_qty ?? 1));
                $panelSummary[$key] = ($panelSummary[$key] ?? 0) + $qty;
            }
        }

        arsort($inverterSummary);
        arsort($bmsSummary);
        arsort($batterySummary);
        arsort($panelSummary);

        // Нестача: потрібно по всіх активних проектах vs склад
        $activeStageIds = [38556547, 69586234, 38556550, 69593822, 69593826, 69593830, 69593834];
        $allEquip = \Illuminate\Support\Facades\DB::table('amocrm_deal_map as m')
            ->join('sales_projects as p', 'p.id', '=', 'm.wallet_project_id')
            ->whereIn('m.amo_status_id', $activeStageIds)
            ->select(
                \Illuminate\Support\Facades\DB::raw('SUM(COALESCE(p.panel_qty, 0)) as panels_needed'),
                \Illuminate\Support\Facades\DB::raw('SUM(COALESCE(p.battery_qty, 0)) as batteries_needed'),
                \Illuminate\Support\Facades\DB::raw("COUNT(CASE WHEN p.inverter IS NOT NULL AND p.inverter != '' AND p.inverter != '-' THEN 1 END) as inverters_needed")
            )->first();

        $stockPanels    = (int) \Illuminate\Support\Facades\DB::table('solarglass_stock')->where('item_name', 'like', 'Фотомодул%')->sum('qty');
        $stockBatteries = (int) \Illuminate\Support\Facades\DB::table('solarglass_stock')->where('item_name', 'like', 'АКБ%')->sum('qty');
        $stockInverters = (int) \Illuminate\Support\Facades\DB::table('solarglass_stock')
            ->where(function ($q) { $q->where('item_name', 'like', 'Інвертор%')->orWhere('item_name', 'like', 'інвертор%'); })->sum('qty');

        $shortage = [
            'panels'    => ['needed' => (int)$allEquip->panels_needed,    'stock' => $stockPanels,    'diff' => $stockPanels    - (int)$allEquip->panels_needed],
            'batteries' => ['needed' => (int)$allEquip->batteries_needed, 'stock' => $stockBatteries, 'diff' => $stockBatteries - (int)$allEquip->batteries_needed],
            'inverters' => ['needed' => (int)$allEquip->inverters_needed, 'stock' => $stockInverters, 'diff' => $stockInverters - (int)$allEquip->inverters_needed],
        ];

        // Деталізований список нестачі по моделях (нормалізовані назви)
        $panelRowsRaw = \Illuminate\Support\Facades\DB::table('amocrm_deal_map as m')
            ->join('sales_projects as p', 'p.id', '=', 'm.wallet_project_id')
            ->whereIn('m.amo_status_id', $activeStageIds)
            ->whereNotNull('p.panel_name')
            ->where('p.panel_name', '!=', '')
            ->select('p.panel_name', 'p.panel_qty')
            ->get();

        $panelsByModelMap = [];
        foreach ($panelRowsRaw as $row) {
            if ($isEmpty($row->panel_name)) continue;
            $key = $normalizeEquipName(trim($row->panel_name));
            $panelsByModelMap[$key] = ($panelsByModelMap[$key] ?? 0) + (int)($row->panel_qty ?? 0);
        }
        arsort($panelsByModelMap);
        $panelsByModel = collect($panelsByModelMap)
            ->map(fn($qty, $name) => ['panel_name' => $name, 'qty' => $qty])
            ->values();

        $batteryRowsRaw = \Illuminate\Support\Facades\DB::table('amocrm_deal_map as m')
            ->join('sales_projects as p', 'p.id', '=', 'm.wallet_project_id')
            ->whereIn('m.amo_status_id', $activeStageIds)
            ->whereNotNull('p.battery_name')
            ->where('p.battery_name', '!=', '')
            ->select('p.battery_name', 'p.battery_qty')
            ->get();

        $batteriesByModelMap = [];
        foreach ($batteryRowsRaw as $row) {
            if ($isEmpty($row->battery_name)) continue;
            $key = $normalizeBatteryName(trim($row->battery_name));
            $batteriesByModelMap[$key] = ($batteriesByModelMap[$key] ?? 0) + (int)($row->battery_qty ?? 0);
        }

        // Нормалізований склад по моделях панелей
        $stockPanelRows = \Illuminate\Support\Facades\DB::table('solarglass_stock')
            ->where('item_name', 'like', 'Фотомодул%')->get(['item_name', 'qty']);
        $stockPanelMap = [];
        foreach ($stockPanelRows as $row) {
            $key = $normalizeEquipName(trim($row->item_name));
            $stockPanelMap[$key] = ($stockPanelMap[$key] ?? 0) + (int)$row->qty;
        }

        // Нормалізований склад по моделях АКБ
        $stockBatteryRows = \Illuminate\Support\Facades\DB::table('solarglass_stock')
            ->where('item_name', 'like', 'АКБ%')->get(['item_name', 'qty']);
        $stockBatteryMap = [];
        foreach ($stockBatteryRows as $row) {
            $key = $normalizeBatteryName(trim($row->item_name));
            $stockBatteryMap[$key] = ($stockBatteryMap[$key] ?? 0) + (int)$row->qty;
        }

        // Таблиця панелей: склад + проекти → нестача / залишок
        $panelsTableMap = [];
        foreach (array_unique(array_merge(array_keys($panelsByModelMap), array_keys($stockPanelMap))) as $key) {
            $inProjects = $panelsByModelMap[$key] ?? 0;
            $inStock    = $stockPanelMap[$key]    ?? 0;
            $panelsTableMap[] = [
                'name'      => $key,
                'stock'     => $inStock,
                'projects'  => $inProjects,
                'shortage'  => max(0, $inProjects - $inStock),
                'remaining' => max(0, $inStock - $inProjects),
            ];
        }
        $panelsTableMap = array_values(array_filter($panelsTableMap, fn($r) => $r['projects'] > 0 || $r['stock'] > 0));
        usort($panelsTableMap, fn($a, $b) => $b['shortage'] <=> $a['shortage'] ?: $b['projects'] <=> $a['projects']);

        // Таблиця АКБ: склад + проекти → нестача / залишок
        $batteriesTableMap = [];
        foreach (array_unique(array_merge(array_keys($batteriesByModelMap), array_keys($stockBatteryMap))) as $key) {
            $inProjects = $batteriesByModelMap[$key] ?? 0;
            $inStock    = $stockBatteryMap[$key]     ?? 0;
            $batteriesTableMap[] = [
                'name'      => $key,
                'stock'     => $inStock,
                'projects'  => $inProjects,
                'shortage'  => max(0, $inProjects - $inStock),
                'remaining' => max(0, $inStock - $inProjects),
            ];
        }
        $batteriesTableMap = array_values(array_filter($batteriesTableMap, fn($r) => $r['projects'] > 0 || $r['stock'] > 0));
        usort($batteriesTableMap, fn($a, $b) => $b['shortage'] <=> $a['shortage'] ?: $b['projects'] <=> $a['projects']);

        // Таблиця інверторів: склад (нормалізований) + проекти
        $stockInverterRows = \Illuminate\Support\Facades\DB::table('solarglass_stock')
            ->where(function ($q) { $q->where('item_name', 'like', 'Інвертор%')->orWhere('item_name', 'like', 'інвертор%'); })
            ->get(['item_name', 'qty']);
        $stockInverterMap = [];
        foreach ($stockInverterRows as $row) {
            $key = $normalizeInverterName(trim($row->item_name));
            $stockInverterMap[$key] = ($stockInverterMap[$key] ?? 0) + (int)$row->qty;
        }
        $invertersTable = [];
        foreach (array_unique(array_merge(array_keys($inverterSummary), array_keys($stockInverterMap))) as $key) {
            $inProjects = $inverterSummary[$key]   ?? 0;
            $inStock    = $stockInverterMap[$key]  ?? 0;
            if ($inProjects === 0 && $inStock === 0) continue;
            $invertersTable[] = [
                'name'      => $key,
                'stock'     => $inStock,
                'projects'  => $inProjects,
                'shortage'  => max(0, $inProjects - $inStock),
                'remaining' => max(0, $inStock - $inProjects),
            ];
        }
        usort($invertersTable, fn($a, $b) => $b['shortage'] <=> $a['shortage'] ?: $b['projects'] <=> $a['projects']);

        $panelsByModel = collect($panelsByModelMap)
            ->map(fn($qty, $name) => ['panel_name' => $name, 'qty' => $qty])
            ->values();
        $batteriesByModel = collect($batteriesByModelMap)
            ->map(fn($qty, $name) => ['battery_name' => $name, 'qty' => $qty])
            ->values();

        return response()->json([
            'projects' => $projects->values(),
            'summary' => [
                'inverter' => $inverterSummary,
                'bms' => $bmsSummary,
                'battery' => $batterySummary,
                'panels' => $panelSummary,
            ],
            'shortage' => $shortage,
            'tables' => [
                'panels'    => $panelsTableMap,
                'batteries' => $batteriesTableMap,
                'inverters' => $invertersTable,
            ],
        ]);
    });

    Route::get('/projects/service-repair', function () {
        $user = auth()->user();

        if (!$user) {
            abort(403);
        }

        $isOwner = $user->role === 'owner';
        $isForeman = $user->role === 'worker' && $user->position === 'foreman';

        if (!$isOwner && !$isForeman) {
            abort(403);
        }

        return view('projects.service-repair');
    })->middleware(['auth']);

    Route::get('/salary', function () {
        return view('salary.index');
    })->middleware(['auth', 'only.owner']);

    Route::get('/salary/electricians', function () {
        return view('salary.electricians.index');
    })->middleware(['auth', 'only.owner']);

    Route::get('/salary/electricians/show', function () {
        return view('salary.electricians.savenkov');
    })->middleware(['auth', 'only.owner']);

    Route::get('/salary/installers', function () {
        return view('salary.installers.index');
    })->middleware(['auth', 'only.owner']);

    Route::get('/salary/installers/show', function () {
        return view('salary.installers.show');
    })->middleware(['auth', 'only.owner']);

});

Route::middleware(['auth', 'only.owner'])
    ->group(function () {

        Route::get('/automation', function () {
            $logs = AutomationLog::latest()->take(10)->get();

            return view('automation.index', compact('logs'));
        })->name('automation.index');

        Route::post('/automation/fx', function (AutomationService $automation) {
            $result = $automation->fxUpdate();

            $status = $result['ok'] ? 'success' : 'error';

            $message = $result['ok']
                ? 'FX updated'
                : 'FX failed';

            AutomationLog::create([
                'user_id' => auth()->id(),
                'action'  => 'fx_update',
                'status'  => $status,
                'message' => $message,
            ]);

            $logs = AutomationLog::latest()
                ->take(10)
                ->get()
                ->map(function ($log) {
                    return [
                        'created_at' => optional($log->created_at)->format('d.m.Y H:i:s'),
                        'user' => optional($log->user)->name
                            ?: optional($log->user)->email
                            ?: ('User #' . $log->user_id),
                        'action' => $log->action,
                        'status' => $log->status,
                        'message' => $log->message,
                    ];
                })
                ->values();

            return response()->json([
                'result' => $result,
                'logs' => $logs,
            ]);
        })->name('automation.fx');

    });



// TEMPORARY: AMO OAuth setup route — delete after tokens are stored
Route::middleware(['auth', 'only.owner'])->group(function () {
    Route::get('/amocrm/setup', function (\App\Services\AmoCrmService $amo) {
        $code = request('code', '');

        if ($code !== '') {
            $result = $amo->exchangeAuthorizationCode($code);
            if ($result['ok'] ?? false) {
                return '<h2 style="color:green">✅ Токени збережено! AMO підключено.</h2>'
                    . '<p>Тепер запустіть: <code>php artisan amocrm:sync-deals</code></p>'
                    . '<p><a href="/amocrm/setup">← Назад</a></p>';
            }
            return '<h2 style="color:red">❌ Помилка: ' . e($result['body'] ?? 'невідома') . '</h2>'
                . '<p>Код вже використано або протух. <a href="/amocrm/setup">Спробувати ще раз</a></p>';
        }

        $clientId = config('services.amocrm.client_id');
        $redirectUri = config('services.amocrm.redirect_uri');
        $amoUrl = 'https://www.amocrm.ru/oauth?client_id=' . $clientId
            . '&state=setup&mode=post_message';

        $tokenCount = DB::table('amocrm_tokens')->count();

        return '<h2>AMO OAuth Setup</h2>'
            . '<p>Токенів у DB: <b>' . $tokenCount . '</b></p>'
            . '<p>redirect_uri: <code>' . e($redirectUri) . '</code></p>'
            . '<hr>'
            . '<p><b>Крок 1:</b> Зайдіть в amoCRM → Налаштування → Інтеграції → ваша інтеграція → натисніть "Дозволити доступ"</p>'
            . '<p><b>Крок 2:</b> Вас перенаправить на <code>' . e($redirectUri) . '?code=XXXX</code> — можливо 404, але код буде в URL</p>'
            . '<p><b>Крок 3:</b> Скопіюйте код з URL і вставте сюди:</p>'
            . '<form method="GET">'
            . '<input name="code" placeholder="Вставте code= значення з URL" style="width:600px;padding:8px" required>'
            . '<button type="submit" style="padding:8px 16px;margin-left:8px">Підключити</button>'
            . '</form>';
    })->name('amocrm.setup');

    Route::get('/amocrm/callback', function (\App\Services\AmoCrmService $amo) {
        $code = request('code', '');
        if ($code === '') {
            return redirect('/amocrm/setup')->with('error', 'Код відсутній');
        }
        $result = $amo->exchangeAuthorizationCode($code);
        if ($result['ok'] ?? false) {
            return '<h2 style="color:green">✅ Токени збережено! AMO підключено.</h2>';
        }
        return '<h2 style="color:red">❌ ' . e($result['body'] ?? 'Помилка') . '</h2>'
            . '<p><a href="/amocrm/setup">← Назад</a></p>';
    })->name('amocrm.callback');
});

require __DIR__ . '/auth.php';
