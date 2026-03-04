<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use App\Http\Controllers\EntryReceiptController;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\DeliveryController;







Route::get('/ping', fn () => response()->json(['ok' => true]));

$runAutomationProjectSync = function (
    Request $request,
    array $options
) {
    if ($request->header('X-AUTO-TOKEN') !== config('services.automation.token')) {
        return response()->json(['ok' => false], 403);
    }

    $rows = $request->input('rows', []);
    $assignmentField = $options['assignment_field'];
    $assignmentValue = $options['assignment_value'] ?? null;
    $dateField = $options['date_field'];
    $noteField = $options['note_field'] ?? null;
    $taskNoteField = $options['task_note_field'] ?? null;
    $serviceTableAvailable = Schema::hasTable('service_requests');

    $normalizeName = function ($value): string {
        $value = mb_strtolower((string) $value);
        $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value);
        $value = preg_replace('/\s+/u', ' ', (string) $value);
        return trim((string) $value);
    };
    $extractTaskMeta = function (string $taskNote): array {
        $meta = [
            'D' => '',
            'E' => '',
            'F' => '',
        ];

        if ($taskNote === '') {
            return $meta;
        }

        foreach (preg_split('/\r\n|\r|\n/u', $taskNote) ?: [] as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }

            if (preg_match('/^([DEF]):\s*(.*)$/u', $line, $m)) {
                $meta[$m[1]] = trim((string) $m[2]);
            }
        }

        return $meta;
    };
    $normalizeSheetDate = function ($value): ?string {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }

        if (preg_match('/^(\d{1,2})[.\/-](\d{1,2})[.\/-](\d{2}|\d{4})$/', $value, $m)) {
            $day = (int) $m[1];
            $month = (int) $m[2];
            $year = (int) $m[3];
            if ($year < 100) {
                $year += 2000;
            }

            if (checkdate($month, $day, $year)) {
                return sprintf('%04d-%02d-%02d', $year, $month, $day);
            }
        }

        if (is_numeric($value)) {
            $serial = (int) $value;
            if ($serial > 0) {
                try {
                    return \Carbon\Carbon::create(1899, 12, 30)->addDays($serial)->format('Y-m-d');
                } catch (\Throwable $e) {
                    return null;
                }
            }
        }

        try {
            return \Carbon\Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    };

    $compactName = function ($value) use ($normalizeName): string {
        return str_replace(' ', '', $normalizeName($value));
    };

    $scoreNameMatch = function (string $needle, string $haystack) use ($normalizeName, $compactName): float {
        $needleNorm = $normalizeName($needle);
        $haystackNorm = $normalizeName($haystack);

        if ($needleNorm === '' || $haystackNorm === '') {
            return 0.0;
        }

        if (str_contains($haystackNorm, $needleNorm) || str_contains($needleNorm, $haystackNorm)) {
            return 100.0;
        }

        $needleCompact = $compactName($needleNorm);
        $haystackCompact = $compactName($haystackNorm);

        if ($needleCompact !== '' && $haystackCompact !== '' && (
            str_contains($haystackCompact, $needleCompact) ||
            str_contains($needleCompact, $haystackCompact)
        )) {
            return 96.0;
        }

        similar_text($needleCompact, $haystackCompact, $mainPercent);
        $best = (float) $mainPercent;

        foreach (preg_split('/\s+/u', $haystackNorm) ?: [] as $token) {
            $token = trim((string) $token);
            if ($token === '') {
                continue;
            }

            similar_text($needleNorm, $token, $tokenPercent);
            if ($tokenPercent > $best) {
                $best = (float) $tokenPercent;
            }

            $tokenCompact = $compactName($token);
            if ($tokenCompact !== '') {
                similar_text($needleCompact, $tokenCompact, $tokenCompactPercent);
                if ($tokenCompactPercent > $best) {
                    $best = (float) $tokenCompactPercent;
                }
            }
        }

        return $best;
    };

    $projectColumns = ['id', 'client_name', $assignmentField];
    if ($noteField) {
        $projectColumns[] = $noteField;
    }
    if ($taskNoteField) {
        $projectColumns[] = $taskNoteField;
    }

    $projects = DB::table('sales_projects')
        ->select($projectColumns)
        ->get()
        ->filter(function ($project) use ($normalizeName, $assignmentField, $assignmentValue) {
            $assignment = $normalizeName($project->{$assignmentField} ?? '');
            if ($assignment === '') {
                return false;
            }

            if ($assignmentValue === null) {
                return true;
            }

            return $assignment === $normalizeName($assignmentValue);
        })
        ->values();

    $checked = 0;
    $updated = 0;
    $serviceCreated = 0;
    $serviceUpdated = 0;
    $notFound = [];
    $skipped = [];
    $skipTokens = ['вихідні', 'сервіси'];
    $scheduleRows = [];

    foreach ($rows as $row) {
        $date = $normalizeSheetDate($row['date'] ?? null);
        $name = trim($row['name'] ?? '');
        $note = $row['note'] ?? null;
        $taskNote = trim((string) ($row['task_note'] ?? ''));

        if (!$name || !$date) {
            continue;
        }

        $parts = preg_split('/[\/\r\n]+/u', $name) ?: [];
        $names = collect($parts)
            ->map(fn ($part) => trim((string) $part))
            ->filter()
            ->values();

        if ($names->isEmpty()) {
            $names = collect([$name]);
        }

        foreach ($names as $candidateName) {
            $cleanName = trim(mb_strtolower($candidateName));
            if ($cleanName === '') {
                continue;
            }

            $checked++;

            if (in_array($cleanName, $skipTokens, true)) {
                $skipped[] = $candidateName;
                continue;
            }

            if ($taskNote !== '' && $serviceTableAvailable) {
                $taskMeta = $extractTaskMeta($taskNote);
                $servicePayload = [
                    'client_name' => $candidateName,
                    'settlement' => $taskMeta['D'] !== '' ? $taskMeta['D'] : 'Автоматизація',
                    'description' => $taskNote,
                    'updated_at' => now(),
                ];

                if ($noteField && $note) {
                    $servicePayload['description'] = trim($taskNote . "\n\nПримітка: " . $note);
                }

                if ($assignmentField === 'electrician') {
                    $servicePayload['electrician'] = $assignmentValue;
                }
                if ($assignmentField === 'installation_team') {
                    $servicePayload['installation_team'] = $assignmentValue;
                }

                $servicePayload['created_at'] = now();
                $servicePayload['status'] = 'open';
                $servicePayload['created_by'] = null;

                DB::table('service_requests')->insert($servicePayload);
                $serviceCreated++;
                continue;
            }

            $project = null;
            $bestCandidate = null;
            $bestScore = 0.0;

            foreach ($projects as $candidateProject) {
                $score = $scoreNameMatch($candidateName, (string) $candidateProject->client_name);
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestCandidate = $candidateProject;
                }
            }

            if ($bestCandidate && $bestScore >= 72.0) {
                $project = $bestCandidate;
            } else {
                $notFound[] = $candidateName;
                continue;
            }

            $update = [
                $dateField => $date,
                'updated_at' => now(),
            ];

            if ($noteField && Schema::hasColumn('sales_projects', $noteField)) {
                $update[$noteField] = $note ?: ($project->{$noteField} ?? null);
            }

            if ($taskNoteField && Schema::hasColumn('sales_projects', $taskNoteField) && $taskNote !== '') {
                $update[$taskNoteField] = $taskNote;
            }

            DB::table('sales_projects')
                ->where('id', $project->id)
                ->update($update);

            if (Schema::hasTable('project_schedule_entries')) {
                $scheduleAssignmentValue = $assignmentValue ?? trim((string) ($project->{$assignmentField} ?? ''));
                if ($scheduleAssignmentValue !== '') {
                    $scheduleKey = implode('|', [
                        (string) $project->id,
                        $assignmentField,
                        $scheduleAssignmentValue,
                        $date,
                    ]);

                    $scheduleRows[$scheduleKey] = [
                        'project_id' => $project->id,
                        'assignment_field' => $assignmentField,
                        'assignment_value' => $scheduleAssignmentValue,
                        'work_date' => $date,
                        'source' => 'automation',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            $updated++;
        }
    }

    if (Schema::hasTable('project_schedule_entries')) {
        $deleteQuery = DB::table('project_schedule_entries')
            ->where('assignment_field', $assignmentField);

        if ($assignmentValue !== null) {
            $deleteQuery->where('assignment_value', $assignmentValue);
        } elseif (!empty($scheduleRows)) {
            $deleteQuery->whereIn(
                'assignment_value',
                array_values(array_unique(array_map(
                    fn ($row) => $row['assignment_value'],
                    $scheduleRows
                )))
            );
        }

        $deleteQuery->delete();

        if (!empty($scheduleRows)) {
            DB::table('project_schedule_entries')->insert(array_values($scheduleRows));
        }
    }

    return response()->json([
        'ok' => true,
        'received_rows' => count($rows),
        'checked' => $checked,
        'updated' => $updated,
        'service_created' => $serviceCreated,
        'service_updated' => $serviceUpdated,
        'skipped' => array_values(array_unique($skipped)),
        'not_found' => array_values(array_unique($notFound)),
    ]);
};

Route::post('/automation/malinin-sync', fn (Request $request) => $runAutomationProjectSync($request, [
    'assignment_field' => 'electrician',
    'assignment_value' => 'Малінін',
    'date_field' => 'electric_work_start_date',
    'note_field' => 'electrician_note',
    'task_note_field' => 'electrician_task_note',
]));

Route::post('/automation/savenkov-sync', fn (Request $request) => $runAutomationProjectSync($request, [
    'assignment_field' => 'electrician',
    'assignment_value' => 'Савенков',
    'date_field' => 'electric_work_start_date',
    'note_field' => 'electrician_note',
    'task_note_field' => 'electrician_task_note',
]));

Route::post('/automation/installers-sync', fn (Request $request) => $runAutomationProjectSync($request, [
    'assignment_field' => 'installation_team',
    'assignment_value' => null,
    'date_field' => 'panel_work_start_date',
    'note_field' => 'installation_team_note',
    'task_note_field' => 'installation_team_task_note',
]));

Route::post('/automation/shevchenko-sync', fn (Request $request) => $runAutomationProjectSync($request, [
    'assignment_field' => 'installation_team',
    'assignment_value' => 'Шевченко',
    'date_field' => 'panel_work_start_date',
    'note_field' => 'installation_team_note',
    'task_note_field' => 'installation_team_task_note',
]));

Route::post('/automation/kukuiaka-sync', fn (Request $request) => $runAutomationProjectSync($request, [
    'assignment_field' => 'installation_team',
    'assignment_value' => 'Кукуяка',
    'date_field' => 'panel_work_start_date',
    'note_field' => 'installation_team_note',
    'task_note_field' => 'installation_team_task_note',
]));

Route::post('/automation/kryzhanovskyi-sync', fn (Request $request) => $runAutomationProjectSync($request, [
    'assignment_field' => 'installation_team',
    'assignment_value' => 'Крижановський',
    'date_field' => 'panel_work_start_date',
    'note_field' => 'installation_team_note',
    'task_note_field' => 'installation_team_task_note',
]));

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


if (!function_exists('erpCashAccount')) {
    function erpCashAccount(string $owner, string $currency): string
    {
        $ownerName = match ($owner) {
            'kolisnyk' => 'Колісник',
            'hlushchenko' => 'Глущенко',
            default => $owner
        };

        return "КЕШ {$ownerName} ({$currency})";
    }
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

$fxRatesResponse = function () {
    if (!Schema::hasTable('fx_rates')) {
        return response()->json(['error' => 'FX table is not ready'], 503);
    }

    $rows = DB::table('fx_rates')
        ->orderBy('currency')
        ->get(['currency', 'buy', 'sell', 'source', 'updated_at']);

    $latestUpdatedAt = $rows->max('updated_at');

    return response()->json([
        'date' => $latestUpdatedAt
            ? \Carbon\Carbon::parse($latestUpdatedAt)->format('d.m.Y H:i')
            : now()->format('d.m.Y H:i'),
        'rates' => $rows->map(fn ($row) => [
            'currency' => (string)$row->currency,
            'purchase' => (float)$row->buy,
            'sale' => (float)$row->sell,
            'source' => (string)($row->source ?? 'manual'),
            'updated_at' => $row->updated_at
                ? \Carbon\Carbon::parse($row->updated_at)->format('d.m.Y H:i')
                : null,
        ])->values(),
    ]);
};

Route::get('/exchange-rates', $fxRatesResponse);
Route::get('/fx/rates', $fxRatesResponse);

Route::post('/fx/update', function (Request $request) {
    if (!Schema::hasTable('fx_rates')) {
        return response()->json(['error' => 'FX table is not ready'], 503);
    }

    $expectedToken = (string) config('services.fx_agent.token');
    $providedToken = (string) (
        $request->bearerToken()
        ?: $request->header('X-FX-TOKEN')
        ?: $request->input('token')
    );

    if ($expectedToken === '' || !hash_equals($expectedToken, $providedToken)) {
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    $data = $request->validate([
        'currency' => 'required|string|size:3',
        'buy' => 'required|numeric|min:0.0001',
        'sell' => 'required|numeric|min:0.0001',
        'source' => 'nullable|string|max:50',
    ]);

    $currency = strtoupper((string)$data['currency']);
    if (!in_array($currency, ['USD', 'EUR'], true)) {
        return response()->json(['error' => 'Unsupported currency'], 422);
    }

    if ((float)$data['buy'] > (float)$data['sell']) {
        return response()->json(['error' => 'Buy rate cannot be higher than sell rate'], 422);
    }

    $payload = [
        'buy' => round((float)$data['buy'], 4),
        'sell' => round((float)$data['sell'], 4),
        'source' => (string)($data['source'] ?? 'agent'),
        'updated_at' => now(),
    ];

    $exists = DB::table('fx_rates')->where('currency', $currency)->exists();
    if ($exists) {
        DB::table('fx_rates')->where('currency', $currency)->update($payload);
    } else {
        DB::table('fx_rates')->insert($payload + [
            'currency' => $currency,
            'created_at' => now(),
        ]);
    }

    $row = DB::table('fx_rates')->where('currency', $currency)->first();

    return response()->json([
        'ok' => true,
        'rate' => [
            'currency' => (string)$row->currency,
            'buy' => (float)$row->buy,
            'sell' => (float)$row->sell,
            'source' => (string)$row->source,
            'updated_at' => \Carbon\Carbon::parse($row->updated_at)->format('d.m.Y H:i'),
        ],
    ]);
});


///////////////////////////////////. Санфікс склад.  /////////////////////////////////////



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
