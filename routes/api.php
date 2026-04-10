<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\EntryReceiptController;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\AmoWebhookController;
use App\Http\Controllers\AI\AIChatController;
use App\Http\Controllers\EmployeeTransferController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\CashDeskController;
use App\Services\GoogleSheetsService;
use App\Services\NameMatcher;







Route::get('/ping', fn () => response()->json(['ok' => true]));
Route::post('/amocrm/webhook', AmoWebhookController::class);

Route::post('/automation/amocrm-sync', function (Request $request) {
    $expectedToken = (string) config('services.automation.token');
    $providedToken = (string) $request->header('X-AUTO-TOKEN', '');

    if ($expectedToken === '' || !hash_equals($expectedToken, $providedToken)) {
        return response()->json(['ok' => false, 'error' => 'Unauthorized'], 403);
    }

    try {
        Artisan::call('amocrm:sync-deals');
        $dealsOutput = trim((string) Artisan::output());

        Artisan::call('amocrm:sync-complectation-projects');
        $complectationOutput = trim((string) Artisan::output());

        Log::info('Automation amoCRM sync completed', [
            'deals_output' => $dealsOutput,
            'complectation_output' => $complectationOutput,
        ]);

        return response()->json([
            'ok' => true,
            'deals' => $dealsOutput,
            'complectation' => $complectationOutput,
        ]);
    } catch (\Throwable $e) {
        Log::error('Automation amoCRM sync failed', [
            'error' => $e->getMessage(),
        ]);

        return response()->json([
            'ok' => false,
            'error' => $e->getMessage(),
        ], 500);
    }
});

Route::get('/telegram/projects', function (Request $request) {
    if ($request->header('X-AUTO-TOKEN') !== config('services.automation.token')) {
        return response()->json(['ok' => false], 403);
    }

    $projects = DB::table('sales_projects')
        ->select('client_name', 'status', 'electrician')
        ->orderByDesc('id')
        ->limit(10)
        ->get();

    return response()->json($projects);
});

Route::get('/telegram/services', function (Request $request) {
    if ($request->header('X-AUTO-TOKEN') !== config('services.automation.token')) {
        return response()->json(['ok' => false], 403);
    }

    if (!Schema::hasTable('service_requests')) {
        return response()->json([]);
    }

    $services = DB::table('service_requests')
        ->select('client_name', 'status', 'electrician', 'installation_team', 'is_urgent')
        ->orderByDesc('id')
        ->limit(10)
        ->get();

    return response()->json($services);
});

Route::get('/telegram/assigned-projects', function (Request $request) {
    if ($request->header('X-AUTO-TOKEN') !== config('services.automation.token')) {
        return response()->json(['ok' => false], 403);
    }

    $name = trim((string) $request->query('name', ''));
    $type = trim((string) $request->query('type', 'auto'));

    if ($name === '') {
        return response()->json(['ok' => false, 'error' => 'Name is required'], 422);
    }

    $normalizedName = trim(mb_strtolower($name));
    $items = [];

    $includeElectrician = in_array($type, ['auto', 'electrician'], true);
    $includeInstallers = in_array($type, ['auto', 'installation_team', 'installer', 'installers'], true);

    if ($includeElectrician) {
        $projects = DB::table('sales_projects')
            ->select('id', 'client_name', 'status', 'electrician', 'installation_team', 'electric_work_start_date')
            ->whereRaw('LOWER(TRIM(electrician)) = ?', [$normalizedName])
            ->orderByDesc('id')
            ->get();

        foreach ($projects as $project) {
            $items[] = [
                'entry_type' => 'project',
                'assignment_type' => 'electrician',
                'entity_id' => (int) $project->id,
                'client_name' => (string) $project->client_name,
                'status' => (string) ($project->status ?? 'active'),
                'electrician' => $project->electrician,
                'installation_team' => $project->installation_team,
                'schedule_date' => $project->electric_work_start_date,
            ];
        }

        if (Schema::hasTable('service_requests')) {
            $services = DB::table('service_requests')
                ->select('id', 'client_name', 'status', 'electrician', 'installation_team', 'is_urgent', 'created_at')
                ->whereRaw('LOWER(TRIM(electrician)) = ?', [$normalizedName])
                ->orderByDesc('id')
                ->get();

            foreach ($services as $service) {
                $items[] = [
                    'entry_type' => 'service',
                    'assignment_type' => 'electrician',
                    'entity_id' => (int) $service->id,
                    'client_name' => (string) $service->client_name,
                    'status' => (string) ($service->status ?? 'open'),
                    'electrician' => $service->electrician,
                    'installation_team' => $service->installation_team,
                    'is_urgent' => (bool) ($service->is_urgent ?? false),
                    'schedule_date' => $service->created_at
                        ? \Carbon\Carbon::parse($service->created_at)->format('Y-m-d')
                        : null,
                ];
            }
        }
    }

    if ($includeInstallers) {
        $projects = DB::table('sales_projects')
            ->select('id', 'client_name', 'status', 'electrician', 'installation_team', 'panel_work_start_date')
            ->whereRaw('LOWER(TRIM(installation_team)) = ?', [$normalizedName])
            ->orderByDesc('id')
            ->get();

        foreach ($projects as $project) {
            $key = 'project:installation_team:' . $project->id;
            $items[$key] = [
                'entry_type' => 'project',
                'assignment_type' => 'installation_team',
                'entity_id' => (int) $project->id,
                'client_name' => (string) $project->client_name,
                'status' => (string) ($project->status ?? 'active'),
                'electrician' => $project->electrician,
                'installation_team' => $project->installation_team,
                'schedule_date' => $project->panel_work_start_date,
            ];
        }

        if (Schema::hasTable('service_requests')) {
            $services = DB::table('service_requests')
                ->select('id', 'client_name', 'status', 'electrician', 'installation_team', 'is_urgent', 'created_at')
                ->whereRaw('LOWER(TRIM(installation_team)) = ?', [$normalizedName])
                ->orderByDesc('id')
                ->get();

            foreach ($services as $service) {
                $key = 'service:installation_team:' . $service->id;
                $items[$key] = [
                    'entry_type' => 'service',
                    'assignment_type' => 'installation_team',
                    'entity_id' => (int) $service->id,
                    'client_name' => (string) $service->client_name,
                    'status' => (string) ($service->status ?? 'open'),
                    'electrician' => $service->electrician,
                    'installation_team' => $service->installation_team,
                    'is_urgent' => (bool) ($service->is_urgent ?? false),
                    'schedule_date' => $service->created_at
                        ? \Carbon\Carbon::parse($service->created_at)->format('Y-m-d')
                        : null,
                ];
            }
        }
    }

    $values = array_values($items);

    usort($values, function (array $a, array $b) {
        $aDate = (string) ($a['schedule_date'] ?? '');
        $bDate = (string) ($b['schedule_date'] ?? '');

        if ($aDate === $bDate) {
            return strcmp($a['client_name'], $b['client_name']);
        }

        return strcmp($aDate, $bDate);
    });

    return response()->json($values);
});

$telegramAutoGuard = function (Request $request) {
    return $request->header('X-AUTO-TOKEN') === config('services.automation.token');
};

$telegramAddAmount = function (array &$bucket, string $currency, float $amount): void {
    $currency = strtoupper(trim($currency)) ?: 'UAH';
    if (!isset($bucket[$currency])) {
        $bucket[$currency] = 0.0;
    }
    $bucket[$currency] += round($amount, 2);
};

$telegramRenderTotals = function (array $bucket): array {
    ksort($bucket);
    return collect($bucket)
        ->map(fn ($amount, $currency) => [
            'currency' => $currency,
            'amount' => round((float) $amount, 2),
        ])
        ->values()
        ->all();
};

$telegramCurrentSalaryPeriod = function (): array {
    $now = now();
    $year = (int) $now->year;
    $month = (int) $now->month;

    if ($year < 2026) {
        $year = 2026;
        $month = 1;
    }

    return [$year, $month];
};

$telegramNormalizeName = function ($value): string {
    return trim(mb_strtolower((string) $value));
};

$telegramCalculateElectricianSalary = function ($project, $rule): float {
    $text = trim((string) ($project->inverter_name ?? ''));
    if ($text === '') {
        return 0.0;
    }

    preg_match('/(\d+(?:[.,]\d+)?)\s*(?:k|kw|квт)/iu', str_replace(',', '.', $text), $match);
    if (!empty($match[1])) {
        $power = (float) str_replace(',', '.', $match[1]);
    } elseif (preg_match('/(\d+(?:[.,]\d+)?)/u', str_replace(',', '.', $text), $match)) {
        $power = (float) str_replace(',', '.', $match[1]);
    } else {
        $power = 0.0;
    }

    $underOrEqual50 = $power <= 50;
    $lower = mb_strtolower($text);
    $isHybrid = (bool) preg_match('/hybrid|гібрид|гибрид|гвбрид/u', $lower);

    if ($isHybrid) {
        return $underOrEqual50
            ? (float) ($rule->piecework_hybrid_le_50 ?? 0)
            : (float) ($rule->piecework_hybrid_gt_50 ?? 0);
    }

    return $underOrEqual50
        ? (float) ($rule->piecework_grid_le_50 ?? 0)
        : (float) ($rule->piecework_grid_gt_50 ?? 0);
};

$telegramCalculateInstallerSalary = function ($project, $rule): float {
    $panelName = str_replace(',', '.', (string) ($project->panel_name ?? ''));
    if (preg_match('/(\d+(?:\.\d+)?)\s*(?:w|wp|вт)/iu', $panelName, $match)) {
        $watts = (float) $match[1];
    } elseif (preg_match('/(\d+(?:\.\d+)?)/u', $panelName, $match)) {
        $watts = (float) $match[1];
    } else {
        $watts = 0.0;
    }

    $qty = (float) ($project->panel_qty ?? 0);
    if ($watts <= 0 || $qty <= 0) {
        return 0.0;
    }

    $totalKw = (int) ceil(($watts * $qty) / 1000);
    $unitRate = (float) ($rule->piecework_unit_rate ?? 0);
    $foremanBonus = (float) ($rule->foreman_bonus ?? 0);

    return ($totalKw * $unitRate) + $foremanBonus;
};

Route::get('/telegram/salary', function (Request $request) use (
    $telegramAutoGuard,
    $telegramAddAmount,
    $telegramRenderTotals,
    $telegramCurrentSalaryPeriod,
    $telegramNormalizeName,
    $telegramCalculateElectricianSalary,
    $telegramCalculateInstallerSalary
) {
    if (!$telegramAutoGuard($request)) {
        return response()->json(['ok' => false], 403);
    }

    $name = trim((string) $request->query('name', ''));
    if ($name === '') {
        return response()->json(['ok' => false, 'error' => 'Name is required'], 422);
    }

    if (!Schema::hasTable('salary_rules')) {
        return response()->json(['ok' => false, 'error' => 'Salary rules table is not ready'], 503);
    }

    [$year, $month] = $telegramCurrentSalaryPeriod();
    $normalizedName = $telegramNormalizeName($name);

    $rule = \App\Models\SalaryRule::query()
        ->get()
        ->first(function ($candidate) use ($telegramNormalizeName, $normalizedName) {
            return $telegramNormalizeName($candidate->staff_name ?? '') === $normalizedName;
        });

    if ($rule) {
        $totals = [];
        $projects = [];

        if ((string) $rule->mode === 'fixed') {
            $telegramAddAmount($totals, (string) ($rule->currency ?? 'UAH'), (float) ($rule->fixed_amount ?? 0));

            if (Schema::hasTable('salary_penalties') && Schema::hasColumn('salary_penalties', 'entry_type')) {
                $adjustments = \App\Models\SalaryPenalty::query()
                    ->where('staff_group', $rule->staff_group)
                    ->where('staff_name', $rule->staff_name)
                    ->where('year', $year)
                    ->where('month', $month)
                    ->get();

                foreach ($adjustments as $adj) {
                    $amount = (float) ($adj->amount ?? 0);
                    if ((string) $adj->entry_type === 'bonus') {
                        $telegramAddAmount($totals, (string) ($rule->currency ?? 'UAH'), $amount);
                    } elseif ((string) $adj->entry_type === 'penalty') {
                        $telegramAddAmount($totals, (string) ($rule->currency ?? 'UAH'), -$amount);
                    }
                }
            }
        } elseif ((string) $rule->staff_group === 'electrician') {
            $matched = \App\Models\SalesProject::query()
                ->orderByDesc('id')
                ->get()
                ->filter(function ($project) use ($telegramNormalizeName, $normalizedName) {
                    return $telegramNormalizeName($project->electrician ?? '') === $normalizedName;
                })
                ->values();

            foreach ($matched as $project) {
                $amount = $telegramCalculateElectricianSalary($project, $rule);
                if ($amount <= 0) {
                    continue;
                }

                $telegramAddAmount($totals, (string) ($rule->currency ?? 'USD'), $amount);
                $projects[] = [
                    'client_name' => (string) $project->client_name,
                    'amount' => round($amount, 2),
                    'currency' => (string) ($rule->currency ?? 'USD'),
                ];
            }
        } elseif ((string) $rule->staff_group === 'installation_team') {
            $matched = \App\Models\SalesProject::query()
                ->orderByDesc('id')
                ->get()
                ->filter(function ($project) use ($telegramNormalizeName, $normalizedName) {
                    return $telegramNormalizeName($project->installation_team ?? '') === $normalizedName;
                })
                ->values();

            foreach ($matched as $project) {
                $amount = $telegramCalculateInstallerSalary($project, $rule);
                if ($amount <= 0) {
                    continue;
                }

                $telegramAddAmount($totals, (string) ($rule->currency ?? 'USD'), $amount);
                $projects[] = [
                    'client_name' => (string) $project->client_name,
                    'amount' => round($amount, 2),
                    'currency' => (string) ($rule->currency ?? 'USD'),
                ];
            }
        }

        return response()->json([
            'ok' => true,
            'year' => $year,
            'month' => $month,
            'staff_group' => (string) $rule->staff_group,
            'staff_name' => (string) $rule->staff_name,
            'mode' => (string) $rule->mode,
            'totals' => $telegramRenderTotals($totals),
            'projects' => $projects,
        ]);
    }

    $manager = \App\Models\User::query()
        ->where('role', 'ntv')
        ->get()
        ->first(function ($candidate) use ($telegramNormalizeName, $normalizedName) {
            return $telegramNormalizeName($candidate->name ?? '') === $normalizedName;
        });

    if ($manager) {
        $acceptedTransfers = DB::table('cash_transfers')
            ->select(
                'project_id',
                DB::raw('SUM(usd_amount) as paid_amount'),
                DB::raw('MAX(created_at) as paid_at')
            )
            ->where('status', 'accepted')
            ->whereNotNull('project_id')
            ->groupBy('project_id')
            ->get()
            ->keyBy('project_id');

        $totals = [];
        $projects = [];

        $managerProjects = \App\Models\SalesProject::query()
            ->where('created_by', $manager->id)
            ->orderByDesc('id')
            ->get();

        foreach ($managerProjects as $project) {
            $transferMeta = $acceptedTransfers->get($project->id);
            if (!$transferMeta) {
                continue;
            }

            $paidAmount = round((float) ($transferMeta->paid_amount ?? 0), 2);
            if ($paidAmount + 0.0001 < (float) $project->total_amount) {
                continue;
            }

            $paidAt = $transferMeta->paid_at ? \Carbon\Carbon::parse($transferMeta->paid_at) : null;
            if (!$paidAt || (int) $paidAt->year !== $year || (int) $paidAt->month !== $month) {
                continue;
            }

            $amount = round((float) $project->total_amount * 0.01, 2);
            $currency = (string) $project->currency;

            $telegramAddAmount($totals, $currency, $amount);
            $projects[] = [
                'client_name' => (string) $project->client_name,
                'amount' => $amount,
                'currency' => $currency,
            ];
        }

        return response()->json([
            'ok' => true,
            'year' => $year,
            'month' => $month,
            'staff_group' => 'manager',
            'staff_name' => (string) ($manager->name ?: $manager->email),
            'mode' => 'piecework',
            'totals' => $telegramRenderTotals($totals),
            'projects' => $projects,
        ]);
    }

    return response()->json(['ok' => false, 'error' => 'Salary subject not found'], 404);
});

Route::get('/telegram/salary-summary', function (Request $request) use (
    $telegramAutoGuard,
    $telegramAddAmount,
    $telegramRenderTotals,
    $telegramCurrentSalaryPeriod,
    $telegramCalculateElectricianSalary,
    $telegramCalculateInstallerSalary
) {
    if (!$telegramAutoGuard($request)) {
        return response()->json(['ok' => false], 403);
    }

    if (!Schema::hasTable('salary_rules')) {
        return response()->json(['ok' => false, 'error' => 'Salary rules table is not ready'], 503);
    }

    [$year, $month] = $telegramCurrentSalaryPeriod();
    $projects = \App\Models\SalesProject::query()->get();

    $categories = [
        'electricians' => [],
        'installers' => [],
        'sales' => [],
        'accountant' => [],
        'foreman' => [],
    ];

    $rules = \App\Models\SalaryRule::query()->get();

    foreach ($rules as $rule) {
        $group = (string) $rule->staff_group;
        $mode = (string) $rule->mode;
        $currency = (string) ($rule->currency ?? 'UAH');
        $nameNorm = mb_strtolower(trim((string) $rule->staff_name));

        if ($group === 'electrician') {
            if ($mode === 'fixed') {
                $telegramAddAmount($categories['electricians'], $currency, (float) ($rule->fixed_amount ?? 0));
            } else {
                foreach ($projects as $project) {
                    if (mb_strtolower(trim((string) ($project->electrician ?? ''))) !== $nameNorm) {
                        continue;
                    }
                    $telegramAddAmount($categories['electricians'], $currency ?: 'USD', $telegramCalculateElectricianSalary($project, $rule));
                }
            }
        } elseif ($group === 'installation_team') {
            if ($mode === 'fixed') {
                $telegramAddAmount($categories['installers'], $currency, (float) ($rule->fixed_amount ?? 0));
            } else {
                foreach ($projects as $project) {
                    if (mb_strtolower(trim((string) ($project->installation_team ?? ''))) !== $nameNorm) {
                        continue;
                    }
                    $telegramAddAmount($categories['installers'], $currency ?: 'USD', $telegramCalculateInstallerSalary($project, $rule));
                }
            }
        } elseif ($group === 'accountant' && $mode === 'fixed') {
            $telegramAddAmount($categories['accountant'], $currency, (float) ($rule->fixed_amount ?? 0));
        } elseif ($group === 'foreman' && $mode === 'fixed') {
            $telegramAddAmount($categories['foreman'], $currency, (float) ($rule->fixed_amount ?? 0));
        }
    }

    $managerResponse = app(\App\Http\Controllers\SalaryRuleController::class)->managerPayoutData(new Request([
        'year' => $year,
        'month' => $month,
    ]));
    $managerPayload = $managerResponse->getData(true);
    foreach (($managerPayload['managers'] ?? []) as $manager) {
        foreach (($manager['totals_by_currency'] ?? []) as $item) {
            $telegramAddAmount($categories['sales'], (string) ($item['currency'] ?? 'UAH'), (float) ($item['amount'] ?? 0));
        }
    }

    return response()->json([
        'ok' => true,
        'year' => $year,
        'month' => $month,
        'categories' => [
            'electricians' => $telegramRenderTotals($categories['electricians']),
            'installers' => $telegramRenderTotals($categories['installers']),
            'sales' => $telegramRenderTotals($categories['sales']),
            'accountant' => $telegramRenderTotals($categories['accountant']),
            'foreman' => $telegramRenderTotals($categories['foreman']),
        ],
    ]);
});

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

    $syncLabel     = $assignmentValue ?? $assignmentField;
    $normalizeName = fn ($value): string => NameMatcher::normalize((string) $value);

    Log::info('[auto-sync] START', ['executor' => $syncLabel, 'rows' => count($rows)]);

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

    $scoreNameMatch = fn (string $needle, string $haystack): float => NameMatcher::score($needle, $haystack);

    $projectColumns = ['id', 'client_name', $assignmentField, 'telegram_group_link', 'geo_location_link'];
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
            if ($assignmentValue === null) {
                return true;
            }

            $assignment = $normalizeName($project->{$assignmentField} ?? '');

            // Включаємо проекти де поле порожнє (ще не призначено) АБО вже відповідає
            return $assignment === '' || $assignment === $normalizeName($assignmentValue);
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

            // ── Find best matching project ────────────────────────────────────
            $project = null;
            $bestCandidate = null;
            $bestScore = 0.0;

            foreach ($projects as $candidateProject) {
                $score = $scoreNameMatch($candidateName, (string) $candidateProject->client_name);
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestCandidate = $candidateProject;
                } elseif ($score === $bestScore && $score >= 72.0 && $bestCandidate !== null) {
                    // Tie: prefer the project that already has the assignment field set
                    $currentHas = ($normalizeName($candidateProject->{$assignmentField} ?? '')) !== '';
                    $bestHas = ($normalizeName($bestCandidate->{$assignmentField} ?? '')) !== '';
                    if ($currentHas && !$bestHas) {
                        $bestCandidate = $candidateProject;
                    }
                }
            }

            if ($bestCandidate && $bestScore >= 72.0) {
                $project = $bestCandidate;
            }

            // ── task_note rows always go to service_requests (never project) ──
            if ($taskNote !== '' && $serviceTableAvailable) {
                $taskMeta = $extractTaskMeta($taskNote);
                $settlement = $taskMeta['D'] !== '' ? $taskMeta['D'] : 'Автоматизація';

                $servicePayload = [
                    'client_name'    => $candidateName,
                    'settlement'     => $settlement,
                    'description'    => $taskNote,
                    'scheduled_date' => $date,
                    'updated_at'     => now(),
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

                // ── Збагачення telegram/geo з відповідного проекту ───────────
                // Шукаємо проект по прізвищу + населеному пункту (в client_name проекту)
                $enrichProject = null;

                // 1) Серед кандидатів з name score >= 60 шукаємо той, де settlement є в client_name
                if ($settlement !== 'Автоматизація') {
                    $settlementLower = mb_strtolower(trim($settlement));
                    $enrichProject = $projects
                        ->filter(function ($p) use ($scoreNameMatch, $candidateName, $settlementLower) {
                            return $scoreNameMatch($candidateName, (string) $p->client_name) >= 60
                                && mb_strpos(mb_strtolower((string) $p->client_name), $settlementLower) !== false;
                        })
                        ->sortByDesc(fn ($p) => $scoreNameMatch($candidateName, (string) $p->client_name))
                        ->first();
                }

                // 2) Fallback: найкращий name match >= 72 (вже знайдений $project)
                if (!$enrichProject && $project) {
                    $enrichProject = $project;
                }

                if ($enrichProject) {
                    $tg  = trim((string) ($enrichProject->telegram_group_link ?? ''));
                    $geo = trim((string) ($enrichProject->geo_location_link ?? ''));
                    if ($tg !== '' && $tg !== ',') {
                        $servicePayload['telegram_group_link'] = $tg;
                    }
                    if ($geo !== '' && $geo !== ',') {
                        $servicePayload['geo_location_link'] = $geo;
                    }
                }
                // ─────────────────────────────────────────────────────────────

                $existingServiceQuery = DB::table('service_requests')
                    ->where('client_name', $candidateName)
                    ->where('description', $servicePayload['description']);

                if ($assignmentField === 'electrician') {
                    $existingServiceQuery->where('electrician', $assignmentValue);
                }

                if ($assignmentField === 'installation_team') {
                    $existingServiceQuery->where('installation_team', $assignmentValue);
                }

                $existingService = $existingServiceQuery
                    ->select('id', 'telegram_group_link', 'geo_location_link')
                    ->orderByDesc('id')
                    ->first();

                if ($existingService) {
                    // Не перетирати вручну виставлені посилання
                    if (!empty($existingService->telegram_group_link)) {
                        unset($servicePayload['telegram_group_link']);
                    }
                    if (!empty($existingService->geo_location_link)) {
                        unset($servicePayload['geo_location_link']);
                    }
                    DB::table('service_requests')
                        ->where('id', $existingService->id)
                        ->update($servicePayload);
                    $serviceUpdated++;
                    continue;
                }

                $servicePayload['created_at'] = now();
                $servicePayload['status']      = 'open';
                $servicePayload['created_by']  = null;

                DB::table('service_requests')->insert($servicePayload);
                $serviceCreated++;
                continue;
            }

            if (!$project) {
                $notFound[] = $candidateName;
                Log::warning('[auto-sync] NOT FOUND', [
                    'executor'   => $syncLabel,
                    'name'       => $candidateName,
                    'best_score' => round($bestScore, 1),
                ]);
                continue;
            }

            Log::info('[auto-sync] MATCHED', [
                'executor'    => $syncLabel,
                'sheet_name'  => $candidateName,
                'project_id'  => $project->id,
                'client_name' => $project->client_name,
                'score'       => round($bestScore, 1),
                'date'        => $date,
            ]);

            // Не перезаписувати дату якщо google_sheet вже є джерелом для цього проекту
            $hasGoogleSheetEntry = Schema::hasTable('project_schedule_entries')
                && DB::table('project_schedule_entries')
                    ->where('project_id', $project->id)
                    ->where('source', 'google_sheet')
                    ->exists();

            $update = [
                'updated_at' => now(),
            ];

            if (!$hasGoogleSheetEntry) {
                $update[$dateField] = $date;
            }

            // Якщо поле порожнє — встановити значення (Малінін/Шевченко/тощо)
            if ($assignmentValue !== null && ($project->{$assignmentField} ?? '') === '') {
                $update[$assignmentField] = $assignmentValue;
            }

            if ($noteField && Schema::hasColumn('sales_projects', $noteField)) {
                $update[$noteField] = $note ?: ($project->{$noteField} ?? null);
            }

            if ($taskNoteField && Schema::hasColumn('sales_projects', $taskNoteField) && $taskNote !== '') {
                $update[$taskNoteField] = $taskNote;
            }

            DB::table('sales_projects')
                ->where('id', $project->id)
                ->update($update);

            if (Schema::hasTable('project_schedule_entries') && !$hasGoogleSheetEntry) {
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

    Log::info('[auto-sync] DONE', [
        'executor'        => $syncLabel,
        'received'        => count($rows),
        'checked'         => $checked,
        'updated'         => $updated,
        'service_created' => $serviceCreated,
        'service_updated' => $serviceUpdated,
        'skipped'         => array_values(array_unique($skipped)),
        'not_found'       => array_values(array_unique($notFound)),
    ]);

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

Route::post('/automation/samoilenko-sync', fn (Request $request) => $runAutomationProjectSync($request, [
    'assignment_field' => 'installation_team',
    'assignment_value' => 'Самойленко',
    'date_field' => 'panel_work_start_date',
    'note_field' => 'installation_team_note',
    'task_note_field' => 'installation_team_task_note',
]));

// ─── Electrician Google Sheet sync (ERP-driven) ───────────────────────────────
// POST /api/automation/electrician-google-sync
//
// Структура таблиці: A=день тижня, B=дата, C=замовник, D=нас.пункт,
//                    E=монтажні роботи, F=сервісні роботи, G=примітки
// Назва листа = прізвище електрика (перше слово поля electrician)
//
// Алгоритм (для кожного проекту з непорожнім полем electrician):
//   1. Знайти лист за прізвищем електрика
//   2. Знайти замовника в стовпчику C
//   3. E є → оновити electric_work_start_date, electrician_task_note
//   4. F є → створити/оновити service_request
//   5. Записати в project_schedule_entries
Route::post('/automation/electrician-google-sync', function (Request $request) {

    if ($request->header('X-AUTO-TOKEN') !== config('services.automation.token')) {
        return response()->json(['ok' => false, 'error' => 'Unauthorized'], 403);
    }

    // ── helpers ──────────────────────────────────────────────────────────────
    $normalizeDate = function ($value): ?string {
        $value = trim((string) $value);
        if ($value === '') return null;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return $value;
        if (preg_match('/^(\d{1,2})[.\/-](\d{1,2})[.\/-](\d{2}|\d{4})$/', $value, $m)) {
            $d = (int) $m[1]; $mo = (int) $m[2]; $y = (int) $m[3];
            if ($y < 100) $y += 2000;
            if (checkdate($mo, $d, $y)) return sprintf('%04d-%02d-%02d', $y, $mo, $d);
        }
        if (is_numeric($value) && (int) $value > 0) {
            try { return \Carbon\Carbon::create(1899, 12, 30)->addDays((int) $value)->format('Y-m-d'); }
            catch (\Throwable) { return null; }
        }
        try { return \Carbon\Carbon::parse($value)->format('Y-m-d'); } catch (\Throwable) { return null; }
    };

    $serviceAvailable  = Schema::hasTable('service_requests');
    $scheduleAvailable = Schema::hasTable('project_schedule_entries');
    $skipWords         = ['вихідні', 'вихідний', 'сервіси'];

    // ── 1. Load all projects with electrician set ────────────────────────────
    $projects = DB::table('sales_projects')
        ->select(['id', 'client_name', 'electrician', 'electric_work_start_date',
                  'electrician_note', 'electrician_task_note'])
        ->whereNotNull('electrician')
        ->where('electrician', '!=', '')
        ->get();

    if ($projects->isEmpty()) {
        return response()->json(['ok' => true, 'projects_checked' => 0,
            'projects_updated' => 0, 'services_created' => 0, 'services_updated' => 0,
            'not_found_on_sheet' => [], 'errors' => [], 'log' => []]);
    }

    // ── 2. Init Google Sheets ────────────────────────────────────────────────
    try {
        $sheets = new GoogleSheetsService();
    } catch (\Throwable $e) {
        Log::error('electrician-google-sync: GoogleSheetsService init failed', ['err' => $e->getMessage()]);
        return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
    }

    $sheetCache       = [];  // tab name → parsed rows (array of arrays)
    $missingSheets    = [];  // tab names that failed to load
    $scheduleRows     = [];
    $serviceOnlyPairs = [];  // [{project_id, assignment_value}] — F only, no E → delete schedule entries

    $report = [
        'projects_checked'   => 0,
        'projects_updated'   => 0,
        'services_created'   => 0,
        'services_updated'   => 0,
        'not_found_on_sheet' => [],
        'errors'             => [],
        'log'                => [],
    ];

    foreach ($projects as $project) {
        $report['projects_checked']++;

        $electrician = trim((string) $project->electrician);
        // Surname = first word of the electrician field = sheet tab name
        $surname = explode(' ', $electrician)[0];

        // ── 3. Load sheet tab (cached) ───────────────────────────────────────
        if (!isset($sheetCache[$surname])) {
            if (in_array($surname, $missingSheets, true)) {
                continue;
            }
            try {
                $sheetCache[$surname] = $sheets->getSheetRows($surname, 'A:G');
            } catch (\Throwable $e) {
                $missingSheets[] = $surname;
                $report['errors'][] = "Лист для електрика '{$surname}' не знайдено";
                Log::warning('electrician-google-sync: sheet not found',
                    ['sheet' => $surname, 'err' => $e->getMessage()]);
                continue;
            }
        }

        $sheetRows = $sheetCache[$surname];
        if (empty($sheetRows)) continue;

        // Skip header row if first cell is not a date-like value
        $startIndex = 0;
        $firstCell  = trim((string) ($sheetRows[0][0] ?? ''));
        if ($firstCell !== '' && !is_numeric($firstCell) && $normalizeDate($firstCell) === null
            && mb_strlen($firstCell) < 20) {
            $startIndex = 1;
        }

        // ── 4. Find client in column C ───────────────────────────────────────
        $clientName = trim((string) $project->client_name);
        $clientLow  = mb_strtolower($clientName);
        $matchedRow = null;

        // Pass 1: exact case-insensitive match, prefer rows with non-empty E or F
        $fallbackRow = null;
        foreach (array_slice($sheetRows, $startIndex) as $cols) {
            $colC = trim((string) ($cols[2] ?? '')); // column C = index 2
            if ($colC === '') continue;
            if (in_array(mb_strtolower($colC), $skipWords, true)) continue;
            if (mb_strtolower($colC) !== $clientLow) continue;

            $hasWork = trim((string) ($cols[4] ?? '')) !== '' // E
                    || trim((string) ($cols[5] ?? '')) !== ''; // F
            if ($hasWork) {
                $matchedRow = $cols;
                break;
            }
            if ($fallbackRow === null) {
                $fallbackRow = $cols; // exact match but both E+F empty
            }
        }
        if ($matchedRow === null) $matchedRow = $fallbackRow;

        // Pass 2: fuzzy fallback (similar_text ≥ 72%)
        if ($matchedRow === null) {
            $bestScore = 0.0;
            foreach (array_slice($sheetRows, $startIndex) as $cols) {
                $colC = trim((string) ($cols[2] ?? ''));
                if ($colC === '') continue;
                if (in_array(mb_strtolower($colC), $skipWords, true)) continue;
                similar_text($clientLow, mb_strtolower($colC), $pct);
                if ($pct > $bestScore) {
                    $bestScore  = (float) $pct;
                    $matchedRow = $cols;
                }
            }
            if ($bestScore < 72.0) $matchedRow = null;
        }

        if ($matchedRow === null) {
            $report['not_found_on_sheet'][] =
                "Замовник '{$clientName}' не знайдено на листі '{$surname}'";
            Log::info('electrician-google-sync: client not found on sheet',
                ['client' => $clientName, 'sheet' => $surname]);
            continue;
        }

        // ── 5. Read matched row columns ──────────────────────────────────────
        $colB = trim((string) ($matchedRow[1] ?? '')); // B = date
        $colD = trim((string) ($matchedRow[3] ?? '')); // D = settlement
        $colE = trim((string) ($matchedRow[4] ?? '')); // E = project works
        $colF = trim((string) ($matchedRow[5] ?? '')); // F = service works
        $colG = trim((string) ($matchedRow[6] ?? '')); // G = special notes

        if ($colE === '' && $colF === '') {
            $report['log'][] =
                "Для замовника '{$clientName}' на листі '{$surname}' немає запланованих робіт";
            continue;
        }

        $date = $normalizeDate($colB);
        if (!$date) {
            $report['errors'][] =
                "Замовник '{$clientName}' (лист '{$surname}'): невалідна дата '{$colB}'";
            Log::warning('electrician-google-sync: invalid date',
                ['client' => $clientName, 'sheet' => $surname, 'raw' => $colB]);
            continue;
        }

        // ── 6a. E is set → update project ───────────────────────────────────
        if ($colE !== '') {
            $update = ['electric_work_start_date' => $date, 'updated_at' => now()];

            if ($colE !== ($project->electrician_task_note ?? '')) {
                $update['electrician_task_note'] = $colE;
            }
            if ($colG !== '' && $colG !== ($project->electrician_note ?? '')) {
                $update['electrician_note'] = $colG;
            }

            DB::table('sales_projects')->where('id', $project->id)->update($update);
            $report['projects_updated']++;
            $report['log'][] =
                "Проект '{$clientName}' ({$electrician}): дата {$date}, роботи: {$colE}";

            if ($scheduleAvailable) {
                // Key = DB unique constraint columns — prevents duplicate inserts
                $key = "{$project->id}|electrician|{$electrician}|{$date}";
                $scheduleRows[$key] = [
                    'project_id'       => $project->id,
                    'assignment_field' => 'electrician',
                    'assignment_value' => $electrician,
                    'work_date'        => $date,
                    'source'           => 'google_sheet',
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ];
            }
        }

        // ── 6b. F is set → create/update service request ────────────────────
        if ($colF !== '' && $serviceAvailable) {
            $description = $colF;
            if ($colG !== '') $description .= "\nПримітки: {$colG}";

            $servicePayload = [
                'client_name'    => $clientName,
                'settlement'     => $colD ?: '',
                'electrician'    => $electrician,
                'description'    => $description,
                'scheduled_date' => $date,
                'status'         => 'open',
                'updated_at'     => now(),
            ];

            $existing = DB::table('service_requests')
                ->where('client_name', $clientName)
                ->where('electrician', $electrician)
                ->where('status', 'open')
                ->orderByDesc('id')
                ->first();

            if ($existing) {
                DB::table('service_requests')
                    ->where('id', $existing->id)
                    ->update($servicePayload);
                $report['services_updated']++;
            } else {
                $servicePayload['created_at'] = now();
                $servicePayload['created_by'] = null;
                DB::table('service_requests')->insert($servicePayload);
                $report['services_created']++;
            }

            // project_schedule_entries only for installation work (colE) — not for service-only rows
            // Track service-only: remove stale schedule entries AND clear electric_work_start_date
            if ($colE === '') {
                DB::table('sales_projects')
                    ->where('id', $project->id)
                    ->whereNotNull('electric_work_start_date')
                    ->update(['electric_work_start_date' => null, 'updated_at' => now()]);
                if ($scheduleAvailable) {
                    $serviceOnlyPairs[] = ['project_id' => $project->id, 'assignment_value' => $electrician];
                }
            }
        }
    }

    // ── 6c. Remove project_schedule_entries for service-only projects ─────────
    // Entries from ANY source (automation, google_sheet) must be removed so the
    // project card doesn't appear in the electrician's calendar — only the service card will.
    if ($scheduleAvailable && !empty($serviceOnlyPairs)) {
        foreach ($serviceOnlyPairs as $pair) {
            DB::table('project_schedule_entries')
                ->where('assignment_field', 'electrician')
                ->where('assignment_value', $pair['assignment_value'])
                ->where('project_id', $pair['project_id'])
                ->delete();
        }
    }

    // ── 7. Upsert project_schedule_entries ───────────────────────────────────
    if ($scheduleAvailable && !empty($scheduleRows)) {
        $electricianValues = array_values(array_unique(
            array_map(fn ($r) => $r['assignment_value'], $scheduleRows)
        ));

        DB::table('project_schedule_entries')
            ->where('assignment_field', 'electrician')
            ->whereIn('assignment_value', $electricianValues)
            ->where('source', 'like', 'google_sheet%')
            ->delete();

        DB::table('project_schedule_entries')->insertOrIgnore(array_values($scheduleRows));
    }

    return response()->json(['ok' => true] + $report);
});

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




Route::middleware(['web', 'auth'])->get('/wallets', function () {

    $user  = auth()->user();
    $query = DB::table('wallets')->where('is_active', 1);

    // manager / worker бачать лише свої гаманці (не бухгалтерські)
    if (in_array($user->role, ['manager', 'worker']) && $user->actor) {
        $query->where('owner', $user->actor);
    }

    $wallets = $query
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



Route::middleware(['web', 'auth'])->get('/wallets/{walletId}/entries', function (int $walletId) {

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
        ->get();

    $transferIds = $entries
        ->pluck('cash_transfer_id')
        ->filter()
        ->map(fn ($id) => (int) $id)
        ->unique()
        ->values();

    $transfersById = $transferIds->isEmpty()
        ? collect()
        : DB::table('cash_transfers')
            ->whereIn('id', $transferIds)
            ->get(['id', 'from_wallet_id', 'to_wallet_id'])
            ->keyBy('id');

    $walletIds = $transfersById
        ->flatMap(fn ($t) => array_filter([(int) ($t->from_wallet_id ?? 0), (int) ($t->to_wallet_id ?? 0)]))
        ->unique()
        ->values();

    $walletOwnersById = $walletIds->isEmpty()
        ? collect()
        : DB::table('wallets')
            ->whereIn('id', $walletIds)
            ->pluck('owner', 'id');

    $entries = $entries->map(function ($e) use ($transfersById, $walletOwnersById) {

            $signed = $e->entry_type === 'income'
                ? (float)$e->amount
                : (float)$e->amount * -1;

            $transfer = isset($e->cash_transfer_id)
                ? $transfersById->get((int) $e->cash_transfer_id)
                : null;

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
                'receipt_path'     => $e->receipt_path,
                'receipt_url'      => $e->receipt_path ? Storage::disk('public')->url($e->receipt_path) : null,
                'cash_transfer_id' => isset($e->cash_transfer_id) ? (int)$e->cash_transfer_id : null,
                'source'           => $e->source ?? null,
                'from_owner'       => $transfer ? ($walletOwnersById->get((int) ($transfer->from_wallet_id ?? 0)) ?? null) : null,
                'to_owner'         => $transfer ? ($walletOwnersById->get((int) ($transfer->to_wallet_id ?? 0)) ?? null) : null,
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






Route::middleware(['web', 'auth'])->delete('/wallets/{walletId}', function (int $walletId) {

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



// ── Хелпер: перевірка що операція від НТВ і запит від owner ─────────────────
if (!function_exists('denyIfNtvTransfer')) {
function denyIfNtvTransfer($entry, $user): ?\Illuminate\Http\JsonResponse {
    if (($entry->source ?? '') === 'ntv_transfer' && optional($user)->role === 'owner') {
        return response()->json([
            'message' => 'Ця операція отримана від НТВ і не може бути змінена. Використовуйте коригуючу операцію.'
        ], 403);
    }
    return null;
}
} // end if !function_exists('denyIfNtvTransfer')

// ── Хелпер: сповіщення Hlushchenko про зміни в операціях ─────────────────────
if (!function_exists('notifyHlushchenko')) {
function notifyHlushchenko(string $title, string $body, array $data = []): void {
    $actor = optional(auth()->user())->actor;
    if ($actor === 'hlushchenko') return; // не сповіщати самого себе

    $hlushchenko = DB::table('users')->where('actor', 'hlushchenko')->first(['id']);
    if (!$hlushchenko) return;

    // Дедуплікація: пропускаємо якщо таке саме сповіщення вже є за останні 30 секунд
    $duplicate = DB::table('notifications')
        ->where('user_id', $hlushchenko->id)
        ->where('title', $title)
        ->where('message', $body)
        ->where('created_at', '>=', now()->subSeconds(30))
        ->exists();
    if ($duplicate) return;

    try {
        app(\App\Services\NotificationService::class)->send(
            (int) $hlushchenko->id,
            $title,
            $body,
            'system',
            $data
        );
    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::error('notifyHlushchenko: ' . $e->getMessage());
    }
}
} // end if !function_exists('notifyHlushchenko')

Route::middleware(['web', 'auth'])->group(function () {
    Route::post('/cash/submit', [CashDeskController::class, 'submit']);
    Route::post('/cash/accept-all', [CashDeskController::class, 'acceptAll']);
    Route::get('/cash/pending-list', [CashDeskController::class, 'pendingList']);
    Route::get('/cash/pending-summary', [CashDeskController::class, 'pendingSummary']);

    Route::put('/entries/{id}', function (int $id, \Illuminate\Http\Request $request) {

        $entry = DB::table('entries')->where('id', $id)->first();

        if (! $entry) {
            return response()->json(['message' => 'Entry not found'], 404);
        }

        if ($denied = denyIfNtvTransfer($entry, auth()->user())) return $denied;

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

        $oldAmount  = (float) $entry->amount;
        $newAmount  = (float) $data['amount'];
        $wallet     = DB::table('wallets')->where('id', $entry->wallet_id)->first(['name', 'currency']);
        $currency   = $wallet->currency ?? '?';
        $walletName = $wallet->name ?? "#{$entry->wallet_id}";

        DB::table('entries')
            ->where('id', $id)
            ->update([
                'amount'        => $newAmount,
                'comment'       => $data['comment'],
                'erp_synced_at' => null,
                'updated_at'    => now(),
            ]);

        $user = auth()->user();
        notifyHlushchenko(
            '⚠️ Зміна фінансової операції',
            implode("\n", [
                "👤 Хто: {$user->name}",
                "📍 Гаманець: {$walletName}",
                "🔧 Дія: Редагування суми",
                "💰 Було: " . number_format($oldAmount, 2, '.', ' ') . " {$currency}",
                "💰 Стало: " . number_format($newAmount, 2, '.', ' ') . " {$currency}",
                "📅 Час: " . now()->format('d.m.Y H:i'),
            ]),
            ['entry_id' => $id]
        );

        return response()->json(['ok' => true]);
    });

    Route::delete('/entries/{id}', function (int $id) {

        $entry = DB::table('entries')->where('id', $id)->first();

        if (! $entry) {
            return response()->json(['message' => 'Entry not found'], 404);
        }

        if ($denied = denyIfNtvTransfer($entry, auth()->user())) return $denied;

        // ❌ Заборона видалення не сьогоднішніх
        if ($entry->posting_date !== now()->toDateString()) {
            return response()->json([
                'message' => 'Видалення дозволено тільки в день створення'
            ], 403);
        }

        $amount     = (float) $entry->amount;
        $entryType  = $entry->entry_type;
        $comment    = $entry->comment ?? '';
        $wallet     = DB::table('wallets')->where('id', $entry->wallet_id)->first(['name', 'currency']);
        $currency   = $wallet->currency ?? '?';
        $walletName = $wallet->name ?? "#{$entry->wallet_id}";

        DB::table('entries')->where('id', $id)->delete();

        $user = auth()->user();
        notifyHlushchenko(
            '❌ Видалено фінансову операцію',
            implode("\n", array_filter([
                "👤 Хто: {$user->name}",
                "📍 Гаманець: {$walletName}",
                "🔧 Тип: " . ($entryType === 'income' ? 'Дохід' : 'Витрата'),
                "💰 Сума: " . number_format($amount, 2, '.', ' ') . " {$currency}",
                ($comment ? "📝 Коментар: {$comment}" : null),
                "📅 Час: " . now()->format('d.m.Y H:i'),
            ])),
            ['entry_id' => $id]
        );

        return response()->json(['ok' => true]);
    });

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

Route::get('/telegram/fx', function (Request $request) use ($fxRatesResponse) {
    if ($request->header('X-AUTO-TOKEN') !== config('services.automation.token')) {
        return response()->json(['ok' => false], 403);
    }

    return $fxRatesResponse();
});

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

// ─── Employee Cash Transfers (Owner → Employee) ────────────────
Route::middleware(['web', 'auth'])->group(function () {
    Route::post('/employee-transfers', [EmployeeTransferController::class, 'store']);
    Route::get('/employee-transfers/pending', [EmployeeTransferController::class, 'pending']);
    Route::get('/employee-transfers/history', [EmployeeTransferController::class, 'history']);
    Route::post('/employee-transfers/{id}/accept', [EmployeeTransferController::class, 'accept']);
    Route::post('/employee-transfers/{id}/decline', [EmployeeTransferController::class, 'decline']);
    Route::post('/employee-transfers/{id}/cancel', [EmployeeTransferController::class, 'cancel']);
});


// ─── Notifications ─────────────────────────────────────────────
Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/notifications',           [NotificationController::class, 'index']);
    Route::get('/notifications/count',     [NotificationController::class, 'count']);
    Route::post('/notifications/read',     [NotificationController::class, 'markRead']);
    Route::post('/push-token',             [NotificationController::class, 'savePushToken']);
});

// ─── Internal Messages ─────────────────────────────────────────
Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/messages/unread',         [MessageController::class, 'unread']);
    Route::get('/messages/{userId}',       [MessageController::class, 'history']);
    Route::post('/messages',               [MessageController::class, 'send']);
});

// ─── AI Financial Assistant ────────────────────────────────────
