<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\WalletController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use App\Models\BankAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use App\Models\BankTransactionRaw;
use App\Http\Controllers\ReclamationsController;

//////////////////////////////////////////////////////////////////////////////////////

//////////////////////////////////////////////////////////////////////////////////////

Route::get('/', function () {

    $bankAccounts = \App\Models\BankAccount::where('is_active', true)->get();

    return view('wallet', [   // твій застосунок
        'bankAccounts' => $bankAccounts,
    ]);

})->middleware(['auth'])->name('home');

//////////////////////////////////////////////////////////////////////////////////////

//////////////////////////////////////////////////////////////////////////////////////


// Breeze після логіну веде на dashboard, а ми перекидаємо на /
Route::get('/dashboard', function () {
    return redirect('/');
})->middleware(['auth', 'verified'])->name('dashboard');

//////////////////////////////////////////////////////////////////////////////////////

//////////////////////////////////////////////////////////////////////////////////////

// Налаштування (якщо хочеш окрему сторінку)
Route::get('/settings', function () {
    return view('dashboard');
})->middleware(['auth'])->name('settings');

//////////////////////////////////////////////////////////////////////////////////////

//////////////////////////////////////////////////////////////////////////////////////

// Профіль (зміна пароля і т.д. у Breeze зазвичай тут)
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

//////////////////////////////////////////////////////////////////////////////////////

//////////////////////////////////////////////////////////////////////////////////////

/**
 * API (залишаємо в web.php, бо ти так уже підняв /api/* і воно працює)
 */
Route::middleware(['auth'])->prefix('api')->group(function () {

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

});

//////////////////////////////////////////////////////////////////////////////////////

//////////////////////////////////////////////////////////////////////////////////////


Route::get('/wallet', [WalletController::class, 'index'])
    ->middleware('auth')
    ->name('wallet.index');


//////////////////////////////////////////////////////////////////////////////////////

//////////////////////////////////////////////////////////////////////////////////////

Route::post('/api/bank/csv-preview', function (\Illuminate\Http\Request $request) {

    if (!$request->hasFile('file')) {
        return response()->json(['error' => 'No file'], 400);
    }

    $file = $request->file('file');

    $content = file_get_contents($file->getRealPath());

    // Укргазбанк = Windows-1251
    $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1251');

    $lines = explode("\n", trim($content));
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
        'rows' => array_slice($rows, 0, 20), // preview 20 рядків
    ]);
})->middleware('auth');

 
//////////////////////////////////////////////////////////////////////////////////////

//////////////////////////////////////////////////////////////////////////////////////

Route::get('/debug/ukrgasbank', function () {

    $token = config('services.ukrgasbank.token');

    if (!$token) {
        return '❌ Token not found';
    }

    $response = Http::withToken($token)
        ->get('https://my.ukrgasbank.com/api/v3/accounts');

    return [
        'status' => $response->status(),
        'body'   => $response->json(),
    ];
})->middleware('auth');






    Route::get('/api/bank/balance', function () {
        $rows = \App\Models\BankTransactionRaw::where('bank_code', 'ukrgasbank')->get();

        $balance = 0;

        foreach ($rows as $r) {
            // ukrgasbank: 1 = income, 2 = expense
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
    })->middleware('auth');


    Route::get('/debug/ukrgasbank/import', function () {

        $token = config('services.ukrgasbank.token');
        if (!$token) return '❌ Token not found';

        $response = Http::withToken($token)
            ->get('https://my.ukrgasbank.com/api/v1/ugb/external/transaction-history', [
                'dateFrom' => now()->subDays(7)->format('Y-m-d'),
                'dateTo'   => now()->format('Y-m-d'),
            ]);

        if (!$response->ok()) {
            return ['status' => $response->status()];
        }

        $rows = $response->json()['rows'] ?? [];
        $inserted = 0;
        $skipped = 0;

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
    })->middleware('auth');




    Route::get('/debug/ukrgasbank/transactions', function () {

        $token = config('services.ukrgasbank.token');

        if (!$token) {
            return '❌ Token not found';
        }

        $response = Http::withToken($token)
            ->get('https://my.ukrgasbank.com/api/v1/ugb/external/transaction-history', [
                'dateFrom' => now()->subDays(7)->format('Y-m-d'),
                'dateTo'   => now()->format('Y-m-d'),
            ]);

        return [
            'status' => $response->status(),
            'body'   => $response->json(),
        ];
    })->middleware('auth');



Route::get('/api/bank/transactions', function () {

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




Route::post('/api/bank/csv-import', function (Request $request) {

    if (!$request->hasFile('file')) {
        return response()->json(['error' => 'No file'], 400);
    }

    $file = $request->file('file');

    $content = mb_convert_encoding(
        file_get_contents($file->getRealPath()),
        'UTF-8',
        'Windows-1251'
    );

    $lines = preg_split("/\r\n|\n|\r/", trim($content));
    $header = str_getcsv(array_shift($lines), ';');

    $inserted = 0;
    $skipped = 0;

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
})->middleware('auth');

//////////////////////////////////////////////////////////////////////////////////////

//////////////////////////////////////////////////////////////////////////////////////

Route::get('/api/bank/accounts', function () {

    $response = Http::withToken(config('services.ukrgasbank.token'))
        ->get('https://my.ukrgasbank.com/api/v3/accounts');

    if (!$response->ok()) {
        return response()->json([], 500);
    }

    $rows = $response->json()['rows'] ?? [];

$accounts = collect($rows)
    ->map(function ($a) {

        $balance =
            $a['balance']['closingBalance']
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
    // ✅ ЛИШЕ НЕНУЛЬОВІ
    ->filter(fn ($a) => abs($a['balance']) > 0.01)
    // ❌ ХОВАЄМО ОВЕРНАЙТ / ТЕХНІЧНІ (МАЛІ СУМИ)
    ->filter(fn ($a) => abs($a['balance']) > 100)
    ->values();


    return response()->json($accounts);
});





Route::get('/api/bank/accounts-sggroup', function () {

    $response = Http::withToken(env('UKRGASBANK_SGGROUP_TOKEN'))
        ->get('https://my.ukrgasbank.com/api/v3/accounts');

    if (!$response->ok()) {
        return response()->json([], 500);
    }

    $rows = $response->json()['rows'] ?? [];

    $accounts = collect($rows)
        ->map(function ($a) {

            $balance =
                $a['balance']['closingBalance']
                ?? $a['balance']['balance']
                ?? 0;

            return [
                'id'       => 'sggroup_' . $a['id'], // ⬅️ унікально
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


Route::get('/api/bank/accounts-solarglass', function () {

    $response = Http::withToken(config('services.ukrgasbank_solarglass.token'))
        ->get('https://my.ukrgasbank.com/api/v3/accounts');

    if (!$response->ok()) {
        return response()->json([], 500);
    }

    $rows = $response->json()['rows'] ?? [];

    $accounts = collect($rows)
        ->filter(fn($a) => ($a['iban'] ?? '') === 'UA413204780000026004924944262')
        ->map(function ($a) {

            $balance =
                $a['balance']['closingBalance']
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



//////////////////////////////////////////////////////////////////////////////////////
//.               Баланс Приват
//////////////////////////////////////////////////////////////////////////////////////

Route::get('/api/bank/accounts-privat', function () {

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
                'name'     => 'ТОВ "СОЛАР ГЛАСС"', // ← твоя компанія
                'currency' => $c['currency'],
                'balance'  => (float) $c['balance'],
                'bankCode' => 'privatbank',
            ];
        })
        ->filter(fn ($a) => abs($a['balance']) > 1)
        ->values();

    return response()->json($accounts);
});


//////////////////////////////////////////////////////////////////////////////////////
//.               Баланс монобанк
//////////////////////////////////////////////////////////////////////////////////////


Route::get('/api/bank/accounts-monobank', function () {

    $token = env('MONOBANK_TOKEN');

    if (!$token) {
        return response()->json([]);
    }

    $res = Http::withHeaders([
        'X-Token' => $token,
    ])->get('https://api.monobank.ua/personal/client-info');

    if (!$res->ok()) {
        return response()->json([]);
    }

        $mainIban = 'UA253220010000026005310038535'; // ← ТУТ ТВОЙ ГОЛОВНИЙ

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


//////////////////////////////////////////////////////////////////////////////////////
//.                транзакції укргазбанк ГРУП
//////////////////////////////////////////////////////////////////////////////////////

Route::get('/api/bank/transactions-sggroup', function (Request $request) {

    $iban = $request->query('iban');

    if (!$iban) {
        return response()->json([], 400);
    }

    $response = Http::withToken(env('UKRGASBANK_SGGROUP_TOKEN'))
        ->get('https://my.ukrgasbank.com/api/v1/ugb/external/transaction-history', [
            // банк сам відфільтрує по рахунку
        ]);

    if (!$response->ok()) {
        return response()->json([], 500);
    }

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
                'amount'  => (float) ($r['SUM_PD_NOM'] ?? 0) * ($isIncome ? 1 : -1),
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

//////////////////////////////////////////////////////////////////////////////////////
//.                транзакції укргазбанк ІНЖЕНІРИНГ
//////////////////////////////////////////////////////////////////////////////////////

Route::get('/api/bank/transactions-engineering', function (Request $request) {

    $iban = $request->query('iban');

    if (!$iban) {
        return response()->json([], 400);
    }

    $response = Http::withToken(config('services.ukrgasbank.token'))
        ->get('https://my.ukrgasbank.com/api/v1/ugb/external/transaction-history');

    if (!$response->ok()) {
        return response()->json([], 500);
    }

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
                'amount'  => (float) ($r['SUM_PD_NOM'] ?? 0) * ($isIncome ? 1 : -1),
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



//////////////////////////////////////////////////////////////////////////////////////
//.                транзакції УКРГАЗ СОЛАР ГЛАСС
//////////////////////////////////////////////////////////////////////////////////////


Route::get('/api/bank/transactions-solarglass', function (Request $request) {

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
                'amount'  => (float) ($r['SUM_PD_NOM'] ?? 0) * ($isIncome ? 1 : -1),
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


//////////////////////////////////////////////////////////////////////////////////////
//.                транзакції ПРИВАТБАНК СОЛАР ГЛАСС
//////////////////////////////////////////////////////////////////////////////////////

Route::get('/api/bank/transactions-privat', function (Request $request) {

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



//////////////////////////////////////////////////////////////////////////////////////
//.                транзакції Монобанк ФОП Колісник
//////////////////////////////////////////////////////////////////////////////////////

Route::get('/api/bank/transactions-monobank', function (Request $request) {

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





Route::get('/reclamations', [ReclamationsController::class, 'index'])
  ->middleware(['auth'])
  ->name('reclamations.index');





require __DIR__.'/auth.php';


