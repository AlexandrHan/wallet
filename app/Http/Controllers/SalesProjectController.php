<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use App\Models\SalesProject;
use App\Models\CashTransfer;
use App\Services\AmoCrmService;

class SalesProjectController extends Controller
{
    private function constructionFieldMeta(): array
    {
        $meta = [
            'telegram_group_link' => ['section' => 'Дані клієнта', 'label' => 'Посилання на Telegram'],
            'geo_location_link' => ['section' => 'Дані клієнта', 'label' => 'Посилання на геолокацію'],
            'has_green_tariff' => ['section' => 'Дані клієнта', 'label' => 'Зелений тариф'],
            'electric_work_start_date' => ['section' => 'Планування', 'label' => 'Дата початку монтажу інверторної частини'],
            'electric_work_days' => ['section' => 'Планування', 'label' => 'Тривалість робіт електрика (днів)'],
            'panel_work_start_date' => ['section' => 'Планування', 'label' => 'Дата початку монтажу ФЕМ'],
            'panel_work_days' => ['section' => 'Планування', 'label' => 'Тривалість монтажу ФЕМ (днів)'],
            'inverter' => ['section' => 'Обладнання', 'label' => 'Інвертор'],
            'bms' => ['section' => 'Обладнання', 'label' => 'BMS'],
            'battery_name' => ['section' => 'Обладнання', 'label' => 'АКБ'],
            'battery_qty' => ['section' => 'Обладнання', 'label' => 'Кількість АКБ'],
            'panel_name' => ['section' => 'Обладнання', 'label' => 'ФЕМ'],
            'panel_qty' => ['section' => 'Обладнання', 'label' => 'Кількість ФЕМ'],
            'electrician' => ['section' => 'Персонал', 'label' => 'Електрик'],
            'installation_team' => ['section' => 'Персонал', 'label' => 'Монтажна бригада'],
            'extra_works' => ['section' => 'Персонал', 'label' => 'Доп. роботи'],
            'defects_note' => ['section' => 'Недоліки', 'label' => 'Опис проблемних місць'],
            'defects_photo_path' => ['section' => 'Недоліки', 'label' => 'Головне фото недоліків'],
        ];

        if (Schema::hasColumn('sales_projects', 'electrician_note')) {
            $meta['electrician_note'] = ['section' => 'Персонал', 'label' => 'Електрик примітки'];
        }

        if (Schema::hasColumn('sales_projects', 'electrician_task_note')) {
            $meta['electrician_task_note'] = ['section' => 'Персонал', 'label' => 'Електрик: завдання з таблиці'];
        }

        if (Schema::hasColumn('sales_projects', 'installation_team_note')) {
            $meta['installation_team_note'] = ['section' => 'Персонал', 'label' => 'Монтажна бригада примітки'];
        }

        if (Schema::hasColumn('sales_projects', 'installation_team_task_note')) {
            $meta['installation_team_task_note'] = ['section' => 'Персонал', 'label' => 'Монтажна бригада: завдання з таблиці'];
        }

        if (Schema::hasColumn('sales_projects', 'phone_number')) {
            $meta = [
                'telegram_group_link' => ['section' => 'Дані клієнта', 'label' => 'Посилання на Telegram'],
                'geo_location_link' => ['section' => 'Дані клієнта', 'label' => 'Посилання на геолокацію'],
                'phone_number' => ['section' => 'Дані клієнта', 'label' => 'Номер телефону'],
                'has_green_tariff' => ['section' => 'Дані клієнта', 'label' => 'Зелений тариф'],
                'electric_work_start_date' => ['section' => 'Планування', 'label' => 'Дата початку монтажу інверторної частини'],
                'electric_work_days' => ['section' => 'Планування', 'label' => 'Тривалість робіт електрика (днів)'],
                'panel_work_start_date' => ['section' => 'Планування', 'label' => 'Дата початку монтажу ФЕМ'],
                'panel_work_days' => ['section' => 'Планування', 'label' => 'Тривалість монтажу ФЕМ (днів)'],
                'inverter' => ['section' => 'Обладнання', 'label' => 'Інвертор'],
                'bms' => ['section' => 'Обладнання', 'label' => 'BMS'],
                'battery_name' => ['section' => 'Обладнання', 'label' => 'АКБ'],
                'battery_qty' => ['section' => 'Обладнання', 'label' => 'Кількість АКБ'],
                'panel_name' => ['section' => 'Обладнання', 'label' => 'ФЕМ'],
                'panel_qty' => ['section' => 'Обладнання', 'label' => 'Кількість ФЕМ'],
                'electrician' => ['section' => 'Персонал', 'label' => 'Електрик'],
                'installation_team' => ['section' => 'Персонал', 'label' => 'Монтажна бригада'],
                'extra_works' => ['section' => 'Персонал', 'label' => 'Доп. роботи'],
                'defects_note' => ['section' => 'Недоліки', 'label' => 'Опис проблемних місць'],
                'defects_photo_path' => ['section' => 'Недоліки', 'label' => 'Головне фото недоліків'],
            ];

            if (Schema::hasColumn('sales_projects', 'electrician_note')) {
                $meta['electrician_note'] = ['section' => 'Персонал', 'label' => 'Електрик примітки'];
            }

            if (Schema::hasColumn('sales_projects', 'electrician_task_note')) {
                $meta['electrician_task_note'] = ['section' => 'Персонал', 'label' => 'Електрик: завдання з таблиці'];
            }

            if (Schema::hasColumn('sales_projects', 'installation_team_note')) {
                $meta['installation_team_note'] = ['section' => 'Персонал', 'label' => 'Монтажна бригада примітки'];
            }

            if (Schema::hasColumn('sales_projects', 'installation_team_task_note')) {
                $meta['installation_team_task_note'] = ['section' => 'Персонал', 'label' => 'Монтажна бригада: завдання з таблиці'];
            }
        }

        return $meta;
    }

    private function historyActorLabel(): string
    {
        $user = auth()->user();

        if (!$user) {
            return 'Невідомий користувач';
        }

        if (!empty($user->name)) {
            return (string)$user->name;
        }

        if (!empty($user->actor)) {
            return (string)$user->actor;
        }

        if (!empty($user->email)) {
            return (string)$user->email;
        }

        return 'Користувач #' . $user->id;
    }

    private function normalizeHistoryValue(string $field, $value): string
    {
        if ($field === 'has_green_tariff') {
            return (bool)$value ? 'Є' : 'Немає';
        }

        if (in_array($field, ['electric_work_start_date', 'panel_work_start_date'], true)) {
            if (!$value) {
                return '—';
            }

            try {
                return \Carbon\Carbon::parse($value)->format('d.m.Y');
            } catch (\Throwable $e) {
                return (string)$value;
            }
        }

        if ($field === 'defects_photo_path') {
            return $value ? 'Фото додано/оновлено' : '—';
        }

        $stringValue = trim((string)($value ?? ''));
        return $stringValue !== '' ? $stringValue : '—';
    }

    private function logProjectHistory(SalesProject $project, array $entries): void
    {
        if (!Schema::hasTable('project_change_logs') || empty($entries)) {
            return;
        }

        $user = auth()->user();
        $now = now();
        $actorName = $this->historyActorLabel();

        $rows = [];
        foreach ($entries as $entry) {
            $rows[] = [
                'project_id' => $project->id,
                'section_name' => (string)($entry['section_name'] ?? 'Інше'),
                'field_name' => (string)($entry['field_name'] ?? 'Зміна'),
                'action_type' => (string)($entry['action_type'] ?? 'update'),
                'old_value' => (string)($entry['old_value'] ?? '—'),
                'new_value' => (string)($entry['new_value'] ?? '—'),
                'actor_name' => $actorName,
                'actor_role' => (string)($user->role ?? ''),
                'created_by' => $user?->id,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('project_change_logs')->insert($rows);
    }

    private function toProjectAmount(float $amount, string $advanceCurrency, string $projectCurrency, ?float $exchangeRate): float
    {
        if ($advanceCurrency === $projectCurrency) {
            return round($amount, 2);
        }

        if (!$exchangeRate || $exchangeRate <= 0) {
            throw new \InvalidArgumentException('EXCHANGE_RATE_REQUIRED');
        }

        // Курс задається так:
        // USD+EUR: EUR->USD (крос), USD+UAH: USD->UAH,
        // UAH+USD: USD->UAH, UAH+EUR: EUR->UAH,
        // EUR+USD: USD->EUR, EUR+UAH: EUR->UAH.
        if ($projectCurrency === 'USD' && $advanceCurrency === 'EUR') return round($amount * $exchangeRate, 2);
        if ($projectCurrency === 'USD' && $advanceCurrency === 'UAH') return round($amount / $exchangeRate, 2);
        if ($projectCurrency === 'UAH' && $advanceCurrency === 'USD') return round($amount * $exchangeRate, 2);
        if ($projectCurrency === 'UAH' && $advanceCurrency === 'EUR') return round($amount * $exchangeRate, 2);
        if ($projectCurrency === 'EUR' && $advanceCurrency === 'USD') return round($amount * $exchangeRate, 2);
        if ($projectCurrency === 'EUR' && $advanceCurrency === 'UAH') return round($amount / $exchangeRate, 2);

        throw new \InvalidArgumentException('UNSUPPORTED_CURRENCY_PAIR');
    }

    private function exchangeRateHint(string $projectCurrency, string $advanceCurrency): string
    {
        if ($projectCurrency === 'USD' && $advanceCurrency === 'EUR') {
            return 'Потрібен крос-курс EUR→USD (приклад: 1 EUR → 1.12 USD).';
        }
        if ($projectCurrency === 'USD' && $advanceCurrency === 'UAH') {
            return 'Потрібен курс USD→UAH (приклад: 1 USD → 43.50 UAH).';
        }
        if ($projectCurrency === 'UAH' && $advanceCurrency === 'USD') {
            return 'Потрібен курс USD→UAH (приклад: 1 USD → 43.50 UAH).';
        }
        if ($projectCurrency === 'UAH' && $advanceCurrency === 'EUR') {
            return 'Потрібен курс EUR→UAH (приклад: 1 EUR → 45.00 UAH).';
        }
        if ($projectCurrency === 'EUR' && $advanceCurrency === 'USD') {
            return 'Потрібен крос-курс USD→EUR (приклад: 1 USD → 0.89 EUR).';
        }
        if ($projectCurrency === 'EUR' && $advanceCurrency === 'UAH') {
            return 'Потрібен курс EUR→UAH (приклад: 1 EUR → 45.00 UAH).';
        }

        return 'Вкажіть курс для цієї валютної пари.';
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'client_name'    => 'required|string',
            'total_amount'   => 'required|numeric|min:0.01',
            'advance_amount' => 'nullable|numeric|min:0',
            'currency'       => 'required|in:UAH,USD,EUR',
            'is_retail'      => 'nullable|boolean',
            'from_wallet_id' => 'nullable|integer',
            'to_wallet_id'   => 'nullable|integer',
            'exchange_rate' => 'nullable|numeric|min:0.000001',
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
            'is_retail'        => (bool)($data['is_retail'] ?? false),
            'created_by'       => auth()->id(),
            'status'           => 'active',
        ]);

        // Якщо є аванс — створюємо pending transfer (старе/необов’язкове, залишаю як у тебе)
        if ($advance > 0 && $request->from_wallet_id && $request->to_wallet_id) {

            $transferCurrency = $data['currency'];
            $exchangeRate = $request->exchange_rate !== null ? (float)$request->exchange_rate : null;

            try {
                // Історично поле називається usd_amount, але тут зберігаємо суму у валюті проєкту.
                $usdAmount = $this->toProjectAmount(
                    (float)$advance,
                    (string)$transferCurrency,
                    (string)$project->currency,
                    $exchangeRate
                );
            } catch (\InvalidArgumentException $e) {
                if ($e->getMessage() === 'EXCHANGE_RATE_REQUIRED') {
                    return response()->json([
                        'error' => '⚠️ Невірний/відсутній курс. ' . $this->exchangeRateHint((string)$project->currency, (string)$transferCurrency)
                    ], 422);
                }
                return response()->json(['error' => 'Некоректна валютна пара'], 422);
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

    public function index(Request $request)
    {
        $amoMapByProjectId = Schema::hasTable('amocrm_deal_map')
            ? DB::table('amocrm_deal_map')
                ->select('wallet_project_id', 'amo_deal_id')
                ->get()
                ->keyBy('wallet_project_id')
            : collect();
        $amoComplectationByProjectId = Schema::hasTable('amo_complectation_projects')
            ? DB::table('amo_complectation_projects')
                ->select('wallet_project_id', 'responsible_name')
                ->whereNotNull('wallet_project_id')
                ->get()
                ->keyBy('wallet_project_id')
            : collect();

        $amoResponsibleCache = [];
        $amoCrmService = app(AmoCrmService::class);

        $userNames = DB::table('users')
            ->select('id', 'name', 'email', 'actor')
            ->get()
            ->mapWithKeys(function ($user) {
                $label = trim((string) ($user->name ?? ''));

                if ($label === '') {
                    $label = trim((string) ($user->actor ?? ''));
                }

                if ($label === '') {
                    $label = trim((string) ($user->email ?? ''));
                }

                if ($label === '') {
                    $label = 'Користувач #' . $user->id;
                }

                return [(int) $user->id => $label];
            });

        $layer = trim((string) $request->query('layer', ''));

        $projectsQuery = SalesProject::query();
        if ($layer === 'finance') {
            $projectsQuery->where(function ($q) {
                $q->where('source_layer', 'finance')
                    ->orWhereNull('source_layer');
            });
        } elseif ($layer === 'projects') {
            $projectsQuery->where('source_layer', 'projects');
        }

        $projects = $projectsQuery->orderByDesc('id')->get()->map(function ($project) {

            $transfers = CashTransfer::where('project_id', $project->id)
                ->orderByDesc('id')
                ->get();

            $paid = 0.0;
            $pending = 0.0;
            $projectCurrency = (string)$project->currency;

            foreach ($transfers as $t) {
                $projectAmount = null;
                try {
                    $projectAmount = $this->toProjectAmount(
                        (float)$t->amount,
                        (string)$t->currency,
                        $projectCurrency,
                        $t->exchange_rate !== null ? (float)$t->exchange_rate : null
                    );
                } catch (\Throwable $e) {
                    $projectAmount = (float)($t->usd_amount ?? 0);
                }

                if ($t->status === 'accepted') {
                    $paid += $projectAmount;
                } elseif ($t->status === 'pending') {
                    $pending += $projectAmount;
                }
            }

            $paid = round($paid, 2);
            $pending = round($pending, 2);

            $pendingTargetOwner = $transfers
                ->where('status', 'pending')
                ->pluck('target_owner')
                ->filter()
                ->first();
            
            $attachments = Schema::hasTable('project_attachments')
                ? DB::table('project_attachments')
                    ->where('project_id', $project->id)
                    ->orderByDesc('id')
                    ->get()
                : collect();

            $scheduleEntries = Schema::hasTable('project_schedule_entries')
                ? DB::table('project_schedule_entries')
                    ->where('project_id', $project->id)
                    ->orderBy('work_date')
                    ->get()
                : collect();

            $electricScheduleDates = $scheduleEntries
                ->where('assignment_field', 'electrician')
                ->pluck('work_date')
                ->values()
                ->all();

            $installerScheduleDates = $scheduleEntries
                ->where('assignment_field', 'installation_team')
                ->pluck('work_date')
                ->values()
                ->all();

            return [
                'id' => $project->id,
                'client_name' => $project->client_name,
                'created_by' => (int) $project->created_by,
                'lead_manager_user_id' => $project->lead_manager_user_id ? (int) $project->lead_manager_user_id : null,
                'total_amount' => (float)$project->total_amount,
                'paid_amount' => $paid,
                'pending_amount' => $pending,
                'remaining_amount' => (float)$project->total_amount - $paid,
                'currency' => $project->currency,
                'is_retail' => Schema::hasColumn('sales_projects', 'is_retail')
                    ? (bool)$project->is_retail
                    : false,
                'status' => $project->status,
                'telegram_group_link' => $project->telegram_group_link,
                'geo_location_link' => $project->geo_location_link,
                'phone_number' => Schema::hasColumn('sales_projects', 'phone_number')
                    ? $project->phone_number
                    : null,
                'has_green_tariff' => (bool)$project->has_green_tariff,
                'electric_work_start_date' => $project->electric_work_start_date,
                'electric_work_days' => Schema::hasColumn('sales_projects', 'electric_work_days')
                    ? $project->electric_work_days
                    : 1,
                'panel_work_start_date' => $project->panel_work_start_date,
                'panel_work_days' => Schema::hasColumn('sales_projects', 'panel_work_days')
                    ? $project->panel_work_days
                    : 1,
                'inverter' => $project->inverter,
                'bms' => $project->bms,
                'battery_name' => $project->battery_name,
                'battery_qty' => $project->battery_qty,
                'panel_name' => $project->panel_name,
                'panel_qty' => $project->panel_qty,
                'electrician' => $project->electrician,
                'electric_schedule_dates' => $electricScheduleDates,
                'electrician_note' => Schema::hasColumn('sales_projects', 'electrician_note')
                    ? $project->electrician_note
                    : null,
                'electrician_task_note' => Schema::hasColumn('sales_projects', 'electrician_task_note')
                    ? $project->electrician_task_note
                    : null,
                'installation_team' => $project->installation_team,
                'installer_schedule_dates' => $installerScheduleDates,
                'installation_team_note' => Schema::hasColumn('sales_projects', 'installation_team_note')
                    ? $project->installation_team_note
                    : null,
                'installation_team_task_note' => Schema::hasColumn('sales_projects', 'installation_team_task_note')
                    ? $project->installation_team_task_note
                    : null,
                'extra_works' => $project->extra_works,
                'defects_note' => $project->defects_note,
                'defects_photo_url' => $project->defects_photo_path
                    ? Storage::disk('public')->url($project->defects_photo_path)
                    : null,
                'closed_at' => $project->closed_at
                    ? \Carbon\Carbon::parse($project->closed_at)->format('d.m.Y H:i')
                    : null,
                'created_at' => $project->created_at->format('d.m.Y H:i'),
                'pending_target_owner' => $pendingTargetOwner,
                'attachments' => $attachments->map(function ($a) {
                    $mime = (string)($a->mime ?? '');
                    $isImage = str_starts_with($mime, 'image/');
                    return [
                        'id' => (int)$a->id,
                        'name' => $a->original_name ?: basename((string)$a->path),
                        'mime' => $mime,
                        'url' => Storage::disk('public')->url($a->path),
                        'is_image' => $isImage,
                    ];
                })->values(),
                'transfers' => $transfers->map(function ($t) use ($projectCurrency) {
                    $projectAmount = null;
                    try {
                        $projectAmount = $this->toProjectAmount(
                            (float)$t->amount,
                            (string)$t->currency,
                            $projectCurrency,
                            $t->exchange_rate !== null ? (float)$t->exchange_rate : null
                        );
                    } catch (\Throwable $e) {
                        $projectAmount = (float)($t->usd_amount ?? 0);
                    }

                    return [
                        'id' => $t->id,
                        'amount' => (float)$t->amount,
                        'currency' => $t->currency,
                        'exchange_rate' => $t->exchange_rate,
                        'usd_amount' => (float)$t->usd_amount,
                        'project_amount' => (float)$projectAmount,
                        'status' => $t->status,
                        'target_owner' => $t->target_owner,
                        'created_at' => \Carbon\Carbon::parse($t->created_at)->format('d.m.Y H:i'),
                    ];
                })->values(),
            ];
        })->map(function ($project) use ($userNames, $amoMapByProjectId, $amoComplectationByProjectId, &$amoResponsibleCache, $amoCrmService) {
            $managerId = (int) ($project['lead_manager_user_id'] ?? 0);
            if ($managerId <= 0) {
                $managerId = (int) ($project['created_by'] ?? 0);
            }

            $managerName = $userNames->get($managerId);

            $projectId = (int) ($project['id'] ?? 0);
            $amoMap = $amoMapByProjectId->get($projectId);

            if ($amoMap) {
                $amoDealId = (int) ($amoMap->amo_deal_id ?? 0);
                if (!array_key_exists($amoDealId, $amoResponsibleCache)) {
                    $responsibleName = null;
                    try {
                        $lead = $amoCrmService->getLeadById($amoDealId);
                        if ($lead) {
                            $responsibleName = trim((string) (
                                data_get($lead, '_embedded.users.0.name')
                                ?? ''
                            ));

                            if ($responsibleName === '') {
                                $responsibleId = (int) ($lead['responsible_user_id'] ?? 0);
                                $amoUser = $amoCrmService->getUserById($responsibleId);
                                $responsibleName = trim((string) ($amoUser['name'] ?? ''));
                            }
                        }
                    } catch (\Throwable $e) {
                        // AMO not configured or unavailable — skip responsible lookup
                    }

                    $amoResponsibleCache[$amoDealId] = $responsibleName ?: null;
                }

                $amoResponsibleName = $amoResponsibleCache[$amoDealId] ?? null;
                if (!empty($amoResponsibleName)) {
                    $managerName = $amoResponsibleName;
                }
            }

            $amoComplectation = $amoComplectationByProjectId->get($projectId);
            $amoComplectationManager = trim((string) ($amoComplectation->responsible_name ?? ''));
            if ($amoComplectationManager !== '') {
                $managerName = $amoComplectationManager;
            }

            $project['manager_name'] = $managerName ?: '—';
            return $project;
        });

        return response()->json($projects);
    }

    public function updateLeadManager(Request $request, $id)
    {
        $project = SalesProject::find($id);

        if (!$project) {
            return response()->json(['error' => 'Проект не знайдено'], 404);
        }

        $user = auth()->user();
        if (!$user || !in_array($user->role, ['owner', 'ntv'], true)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        if (!Schema::hasColumn('sales_projects', 'lead_manager_user_id')) {
            return response()->json(['error' => 'Поле ведучого менеджера ще не створено. Запустіть міграції.'], 422);
        }

        $data = $request->validate([
            'lead_manager_user_id' => 'nullable|integer',
        ]);

        $leadManagerUserId = $data['lead_manager_user_id'] ?? null;

        if ($leadManagerUserId !== null) {
            $leadManager = DB::table('users')
                ->where('id', (int) $leadManagerUserId)
                ->where('role', 'ntv')
                ->first();

            if (!$leadManager) {
                return response()->json(['error' => 'Оберіть коректного менеджера НТВ'], 422);
            }

            $leadManagerUserId = (int) $leadManagerUserId;
        }

        $project->lead_manager_user_id = $leadManagerUserId;
        $project->save();

        return response()->json(['ok' => true]);
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
        $exchangeRate = $data['exchange_rate'] !== null ? (float)$data['exchange_rate'] : null;
        $usdAmount = null;

        try {
            // Історично поле називається usd_amount, але тут зберігаємо суму у валюті проєкту.
            $usdAmount = $this->toProjectAmount(
                $amount,
                (string)$currency,
                (string)$project->currency,
                $exchangeRate
            );
        } catch (\InvalidArgumentException $e) {
            if ($e->getMessage() === 'EXCHANGE_RATE_REQUIRED') {
                return response()->json([
                    'error' => '⚠️ Невірний/відсутній курс. ' . $this->exchangeRateHint((string)$project->currency, (string)$currency)
                ], 422);
            }

            return response()->json(['error' => 'Некоректна валютна пара'], 422);
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

    public function updateConstruction(Request $request, $id)
    {
        $project = SalesProject::find($id);
        if (!$project) {
            return response()->json(['error' => 'Проект не знайдено'], 404);
        }

        $rules = [
            'telegram_group_link' => 'nullable|string|max:1000',
            'geo_location_link' => 'nullable|string|max:1000',
            'has_green_tariff' => 'nullable|boolean',
            'electric_work_start_date' => 'nullable|date',
            'electric_work_days' => 'nullable|integer|min:1|max:365',
            'panel_work_start_date' => 'nullable|date',
            'panel_work_days' => 'nullable|integer|min:1|max:365',
            'inverter' => 'nullable|string|max:255',
            'bms' => 'nullable|string|max:255',
            'battery_name' => 'nullable|string|max:255',
            'battery_qty' => 'nullable|integer|min:0|max:1000000',
            'panel_name' => 'nullable|string|max:255',
            'panel_qty' => 'nullable|integer|min:0|max:1000000',
            'electrician' => 'nullable|string|max:255',
            'installation_team' => 'nullable|string|max:255',
            'extra_works' => 'nullable|string|max:1000',
            'defects_note' => 'nullable|string|max:5000',
            'defects_photo' => 'nullable|image|max:10240',
            'photos' => 'nullable|array',
            'photos.*' => 'image|max:10240',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|max:20480',
        ];

        if (Schema::hasColumn('sales_projects', 'phone_number')) {
            $rules['phone_number'] = 'nullable|string|max:50';
        }

        if (Schema::hasColumn('sales_projects', 'electrician_note')) {
            $rules['electrician_note'] = 'nullable|string|max:2000';
        }

        if (Schema::hasColumn('sales_projects', 'electrician_task_note')) {
            $rules['electrician_task_note'] = 'nullable|string|max:5000';
        }

        if (Schema::hasColumn('sales_projects', 'installation_team_note')) {
            $rules['installation_team_note'] = 'nullable|string|max:2000';
        }

        if (Schema::hasColumn('sales_projects', 'installation_team_task_note')) {
            $rules['installation_team_task_note'] = 'nullable|string|max:5000';
        }

        $data = $request->validate($rules);

        $meta = $this->constructionFieldMeta();
        $before = [];
        foreach (array_keys($meta) as $field) {
            $before[$field] = $project->{$field};
        }

        $data['has_green_tariff'] = (bool)($data['has_green_tariff'] ?? false);
        if (!Schema::hasColumn('sales_projects', 'phone_number')) {
            unset($data['phone_number']);
        }
        if (!Schema::hasColumn('sales_projects', 'electric_work_days')) {
            unset($data['electric_work_days']);
        }
        if (!Schema::hasColumn('sales_projects', 'panel_work_days')) {
            unset($data['panel_work_days']);
        }
        if (!Schema::hasColumn('sales_projects', 'electrician_note')) {
            unset($data['electrician_note']);
        }
        if (!Schema::hasColumn('sales_projects', 'electrician_task_note')) {
            unset($data['electrician_task_note']);
        }
        if (!Schema::hasColumn('sales_projects', 'installation_team_note')) {
            unset($data['installation_team_note']);
        }
        if (!Schema::hasColumn('sales_projects', 'installation_team_task_note')) {
            unset($data['installation_team_task_note']);
        }
        $hasDefectsPhotoUpload = $request->hasFile('defects_photo');
        $photoUploads = (array)$request->file('photos', []);
        $attachmentUploads = (array)$request->file('attachments', []);
        $hasPhotoUploads = count(array_filter($photoUploads)) > 0;
        $hasAttachmentUploads = count(array_filter($attachmentUploads)) > 0;

        $historyEntries = [];
        foreach ($meta as $field => $config) {
            if ($field === 'defects_photo_path') {
                continue;
            }

            if (!array_key_exists($field, $data)) {
                continue;
            }

            $oldNormalized = $this->normalizeHistoryValue($field, $before[$field] ?? null);
            $newNormalized = $this->normalizeHistoryValue($field, $data[$field]);

            if ($oldNormalized === $newNormalized) {
                continue;
            }

            $historyEntries[] = [
                'section_name' => $config['section'],
                'field_name' => $config['label'],
                'action_type' => 'update',
                'old_value' => $oldNormalized,
                'new_value' => $newNormalized,
            ];
        }

        if (!$hasDefectsPhotoUpload && !$hasPhotoUploads && !$hasAttachmentUploads && empty($historyEntries)) {
            return response()->json([
                'ok' => true,
                'project' => [
                    'id' => $project->id,
                    'defects_photo_url' => $project->defects_photo_path
                        ? Storage::disk('public')->url($project->defects_photo_path)
                        : null,
                    'closed_at' => $project->closed_at
                        ? \Carbon\Carbon::parse($project->closed_at)->format('d.m.Y H:i')
                        : null,
                    'status' => $project->status,
                ],
            ]);
        }

        if ($hasDefectsPhotoUpload) {
            if ($project->defects_photo_path) {
                Storage::disk('public')->delete($project->defects_photo_path);
            }

            $data['defects_photo_path'] = $request->file('defects_photo')->store('project-defects', 'public');
            $historyEntries[] = [
                'section_name' => $meta['defects_photo_path']['section'],
                'field_name' => $meta['defects_photo_path']['label'],
                'action_type' => 'update',
                'old_value' => $this->normalizeHistoryValue('defects_photo_path', $before['defects_photo_path'] ?? null),
                'new_value' => $this->normalizeHistoryValue('defects_photo_path', $data['defects_photo_path']),
            ];
        }

        unset($data['defects_photo']);
        unset($data['photos']);
        unset($data['attachments']);
        $project->update($data);

        if (Schema::hasTable('project_attachments')) {
            $photoCount = 0;
            foreach ($photoUploads as $file) {
                if (!$file) continue;
                $photoCount++;
                $path = $file->store('project-attachments', 'public');
                DB::table('project_attachments')->insert([
                    'project_id' => $project->id,
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime' => $file->getMimeType(),
                    'size' => $file->getSize(),
                    'created_by' => auth()->id(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $attachmentCount = 0;
            foreach ($attachmentUploads as $file) {
                if (!$file) continue;
                $attachmentCount++;
                $path = $file->store('project-attachments', 'public');
                DB::table('project_attachments')->insert([
                    'project_id' => $project->id,
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime' => $file->getMimeType(),
                    'size' => $file->getSize(),
                    'created_by' => auth()->id(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if ($photoCount > 0) {
                $historyEntries[] = [
                    'section_name' => 'Фото та файли',
                    'field_name' => 'Фото з телефону',
                    'action_type' => 'upload',
                    'old_value' => '—',
                    'new_value' => 'Додано фото: ' . $photoCount,
                ];
            }

            if ($attachmentCount > 0) {
                $historyEntries[] = [
                    'section_name' => 'Фото та файли',
                    'field_name' => 'Файли',
                    'action_type' => 'upload',
                    'old_value' => '—',
                    'new_value' => 'Додано файлів: ' . $attachmentCount,
                ];
            }
        }

        $this->logProjectHistory($project, $historyEntries);

        return response()->json([
            'ok' => true,
            'project' => [
                'id' => $project->id,
                'defects_photo_url' => $project->defects_photo_path
                    ? Storage::disk('public')->url($project->defects_photo_path)
                    : null,
                'closed_at' => $project->closed_at
                    ? \Carbon\Carbon::parse($project->closed_at)->format('d.m.Y H:i')
                    : null,
                'status' => $project->status,
            ],
        ]);
    }

    public function closeProject(Request $request, $id)
    {
        $project = SalesProject::find($id);
        if (!$project) {
            return response()->json(['error' => 'Проект не знайдено'], 404);
        }

        if (trim((string)($project->defects_note ?? '')) !== '') {
            return response()->json([
                'error' => 'Не можна закрити проект, поки заповнені недоліки'
            ], 422);
        }

        if ($project->status === 'completed') {
            return response()->json(['ok' => true]);
        }

        $project->status = 'completed';
        $project->closed_at = now();
        $project->closed_by = auth()->id();
        $project->save();

        $this->logProjectHistory($project, [[
            'section_name' => 'Статус',
            'field_name' => 'Закриття проекту',
            'action_type' => 'close',
            'old_value' => 'Активний',
            'new_value' => 'Завершений',
        ]]);

        return response()->json([
            'ok' => true,
            'closed_at' => $project->closed_at->format('d.m.Y H:i'),
        ]);
    }

    public function projectHistory($id)
    {
        $project = SalesProject::find($id);
        if (!$project) {
            return response()->json(['error' => 'Проект не знайдено'], 404);
        }

        if (!Schema::hasTable('project_change_logs')) {
            return response()->json(['history' => []]);
        }

        $rows = DB::table('project_change_logs')
            ->where('project_id', $project->id)
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'history' => $rows->map(function ($row) {
                return [
                    'id' => (int)$row->id,
                    'section_name' => (string)$row->section_name,
                    'field_name' => (string)$row->field_name,
                    'action_type' => (string)$row->action_type,
                    'old_value' => (string)$row->old_value,
                    'new_value' => (string)$row->new_value,
                    'actor_name' => (string)$row->actor_name,
                    'actor_role' => (string)($row->actor_role ?? ''),
                    'created_at' => \Carbon\Carbon::parse($row->created_at)->format('d.m.Y H:i'),
                ];
            })->values(),
        ]);
    }

    public function constructionStaffOptions()
    {
        $defaults = [
            'electrician' => ['Малінін', 'Савенков', 'Комаренко'],
            'installation_team' => ['Кукуяка', 'Шевченко', 'Крижановський'],
        ];

        if (!Schema::hasTable('construction_staff_options')) {
            return response()->json([
                'electrician' => collect($defaults['electrician'])->map(fn ($name) => ['id' => null, 'name' => $name])->values(),
                'installation_team' => collect($defaults['installation_team'])->map(fn ($name) => ['id' => null, 'name' => $name])->values(),
            ]);
        }

        foreach (['electrician', 'installation_team'] as $type) {
            $hasRows = DB::table('construction_staff_options')->where('type', $type)->exists();
            if (!$hasRows) {
                foreach ($defaults[$type] as $name) {
                    DB::table('construction_staff_options')->insert([
                        'type' => $type,
                        'name' => $name,
                        'created_by' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        $rows = DB::table('construction_staff_options')
            ->whereIn('type', ['electrician', 'installation_team'])
            ->orderBy('name')
            ->get(['id', 'type', 'name']);

        $result = [
            'electrician' => [],
            'installation_team' => [],
        ];

        foreach ($rows as $row) {
            $result[$row->type][] = [
                'id' => (int)$row->id,
                'name' => $row->name,
            ];
        }

        return response()->json($result);
    }

    public function addConstructionStaffOption(Request $request)
    {
        $user = auth()->user();
        if (!$user || $user->role !== 'owner') {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'type' => 'required|in:electrician,installation_team',
            'name' => 'required|string|min:2|max:255',
        ]);

        if (!Schema::hasTable('construction_staff_options')) {
            return response()->json(['error' => 'Довідник співробітників ще не ініціалізовано'], 422);
        }

        $name = trim($data['name']);
        if ($name === '') {
            return response()->json(['error' => 'Вкажіть імʼя'], 422);
        }

        $exists = DB::table('construction_staff_options')
            ->where('type', $data['type'])
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->exists();

        if ($exists) {
            return response()->json(['ok' => true, 'already_exists' => true]);
        }

        DB::table('construction_staff_options')->insert([
            'type' => $data['type'],
            'name' => $name,
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['ok' => true]);
    }

    public function deleteConstructionStaffOption($id)
    {
        $user = auth()->user();
        if (!$user || $user->role !== 'owner') {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        if (!Schema::hasTable('construction_staff_options')) {
            return response()->json(['error' => 'Довідник співробітників ще не ініціалізовано'], 422);
        }

        DB::table('construction_staff_options')
            ->where('id', (int)$id)
            ->delete();

        return response()->json(['ok' => true]);
    }
}
