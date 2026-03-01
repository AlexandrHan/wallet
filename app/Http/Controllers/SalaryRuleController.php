<?php

namespace App\Http\Controllers;

use App\Models\SalaryPenalty;
use App\Models\SalaryRule;
use App\Models\SalesProject;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SalaryRuleController extends Controller
{
    private function hasInstallerRateColumns(): bool
    {
        return Schema::hasTable('salary_rules')
            && Schema::hasColumn('salary_rules', 'piecework_unit_rate')
            && Schema::hasColumn('salary_rules', 'foreman_bonus');
    }

    private function defaultConstructionStaff(string $type): array
    {
        return match ($type) {
            'electrician' => ['Малінін', 'Савенков', 'Комаренко'],
            'installation_team' => ['Кукуяка', 'Шевченко', 'Крижановський'],
            default => [],
        };
    }

    private function ensureDefaultRules(): void
    {
        if (!Schema::hasTable('salary_rules')) {
            return;
        }

        $defaults = [
            [
                'staff_group' => 'electrician',
                'staff_name' => 'Малінін',
                'mode' => 'fixed',
                'currency' => 'UAH',
                'fixed_amount' => 30000,
                'piecework_grid_le_50' => null,
                'piecework_grid_gt_50' => null,
                'piecework_hybrid_le_50' => null,
                'piecework_hybrid_gt_50' => null,
            ],
            [
                'staff_group' => 'electrician',
                'staff_name' => 'Савенков',
                'mode' => 'piecework',
                'currency' => 'USD',
                'fixed_amount' => null,
                'piecework_grid_le_50' => 150,
                'piecework_grid_gt_50' => 200,
                'piecework_hybrid_le_50' => 200,
                'piecework_hybrid_gt_50' => 300,
            ],
            [
                'staff_group' => 'electrician',
                'staff_name' => 'Комаренко',
                'mode' => 'piecework',
                'currency' => 'USD',
                'fixed_amount' => null,
                'piecework_grid_le_50' => 150,
                'piecework_grid_gt_50' => 200,
                'piecework_hybrid_le_50' => 200,
                'piecework_hybrid_gt_50' => 300,
            ],
        ];

        foreach ($defaults as $row) {
            SalaryRule::query()->firstOrCreate(
                [
                    'staff_group' => $row['staff_group'],
                    'staff_name' => $row['staff_name'],
                ],
                $row + ['created_by' => auth()->id()]
            );
        }

        if ($this->hasInstallerRateColumns()) {
            foreach ($this->defaultConstructionStaff('installation_team') as $teamName) {
                SalaryRule::query()->firstOrCreate(
                    [
                        'staff_group' => 'installation_team',
                        'staff_name' => $teamName,
                    ],
                    [
                        'mode' => 'piecework',
                        'currency' => 'USD',
                        'fixed_amount' => null,
                        'piecework_unit_rate' => 37,
                        'foreman_bonus' => 50,
                        'piecework_grid_le_50' => null,
                        'piecework_grid_gt_50' => null,
                        'piecework_hybrid_le_50' => null,
                        'piecework_hybrid_gt_50' => null,
                        'created_by' => auth()->id(),
                    ]
                );
            }
        }
    }

    private function constructionStaffOptions(string $type): array
    {
        if (!Schema::hasTable('construction_staff_options')) {
            return $this->defaultConstructionStaff($type);
        }

        $rows = DB::table('construction_staff_options')
            ->where('type', $type)
            ->orderBy('name')
            ->pluck('name')
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->values()
            ->all();

        if (!$rows) {
            return $this->defaultConstructionStaff($type);
        }

        return $rows;
    }

    private function subjectOptions(): array
    {
        $managerNames = User::query()
            ->where('role', 'ntv')
            ->orderBy('name')
            ->pluck('name')
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->values()
            ->all();

        $accountantNames = User::query()
            ->where('role', 'accountant')
            ->orderBy('name')
            ->pluck('name')
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->values()
            ->all();

        $foremanNames = User::query()
            ->where('role', 'worker')
            ->where('position', 'foreman')
            ->orderBy('name')
            ->pluck('name')
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->values()
            ->all();

        return [
            'electrician' => $this->constructionStaffOptions('electrician'),
            'installation_team' => $this->constructionStaffOptions('installation_team'),
            'manager' => $managerNames,
            'accountant' => $accountantNames,
            'foreman' => $foremanNames,
        ];
    }

    private function supportsSalaryPenalties(): bool
    {
        return Schema::hasTable('salary_penalties')
            && Schema::hasColumn('salary_penalties', 'entry_type');
    }

    private function userDisplayName(?User $user): string
    {
        if (!$user) {
            return 'Співробітник';
        }

        $name = trim((string) ($user->name ?? ''));
        if ($name !== '') {
            return $name;
        }

        $actor = trim((string) ($user->actor ?? ''));
        if ($actor !== '') {
            return $actor;
        }

        $email = trim((string) ($user->email ?? ''));
        if ($email !== '') {
            return $email;
        }

        return 'Користувач #' . $user->id;
    }

    private function buildFixedEmployeePayload(string $staffGroup, string $staffName, int $year): array
    {
        $rule = Schema::hasTable('salary_rules')
            ? SalaryRule::query()
                ->where('staff_group', $staffGroup)
                ->where('staff_name', $staffName)
                ->first()
            : null;

        $monthlyAmount = round((float) ($rule?->fixed_amount ?? 0), 2);
        $currency = (string) ($rule?->currency ?? 'UAH');
        $mode = (string) ($rule?->mode ?? 'fixed');

        $adjustmentsByMonth = collect();
        if ($this->supportsSalaryPenalties()) {
            $adjustmentsByMonth = SalaryPenalty::query()
                ->where('staff_group', $staffGroup)
                ->where('staff_name', $staffName)
                ->where('year', $year)
                ->orderBy('month')
                ->orderBy('entry_type')
                ->orderBy('sort_order')
                ->get()
                ->groupBy('month');
        }

        $months = collect(range(1, 12))->map(function (int $month) use ($adjustmentsByMonth, $monthlyAmount) {
            $rows = collect($adjustmentsByMonth->get($month, []));

            $bonuses = $rows
                ->where('entry_type', 'bonus')
                ->map(function ($row) {
                    return [
                        'id' => (int) $row->id,
                        'amount' => round((float) $row->amount, 2),
                        'description' => (string) ($row->description ?? ''),
                    ];
                })
                ->values();

            $penalties = $rows
                ->where('entry_type', 'penalty')
                ->map(function ($row) {
                    return [
                        'id' => (int) $row->id,
                        'amount' => round((float) $row->amount, 2),
                        'description' => (string) ($row->description ?? ''),
                    ];
                })
                ->values();

            $bonusTotal = round((float) $bonuses->sum('amount'), 2);
            $penaltyTotal = round((float) $penalties->sum('amount'), 2);

            return [
                'month' => $month,
                'monthly_amount' => $monthlyAmount,
                'bonus_total' => $bonusTotal,
                'penalty_total' => $penaltyTotal,
                'net_amount' => round($monthlyAmount + $bonusTotal - $penaltyTotal, 2),
                'bonuses' => $bonuses,
                'penalties' => $penalties,
            ];
        })->values();

        return [
            'view_type' => 'fixed',
            'staff_group' => $staffGroup,
            'staff_name' => $staffName,
            'year' => $year,
            'mode' => $mode,
            'currency' => $currency,
            'monthly_amount' => $monthlyAmount,
            'months' => $months,
        ];
    }

    private function buildManagerYearPayload(User $manager, int $year): array
    {
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

        $projects = SalesProject::query()
            ->where('created_by', $manager->id)
            ->orderByDesc('id')
            ->get();

        $monthsMap = [];
        foreach (range(1, 12) as $month) {
            $monthsMap[$month] = [
                'totals_by_currency' => [],
                'projects' => [],
            ];
        }

        foreach ($projects as $project) {
            $transferMeta = $acceptedTransfers->get($project->id);
            if (!$transferMeta) {
                continue;
            }

            $paidAmount = round((float) ($transferMeta->paid_amount ?? 0), 2);
            if ($paidAmount + 0.0001 < (float) $project->total_amount) {
                continue;
            }

            $paidAt = $transferMeta->paid_at ? \Carbon\Carbon::parse($transferMeta->paid_at) : null;
            if (!$paidAt || (int) $paidAt->year !== $year) {
                continue;
            }

            $month = (int) $paidAt->month;
            $currency = (string) $project->currency;
            $commission = round((float) $project->total_amount * 0.01, 2);

            if (!isset($monthsMap[$month]['totals_by_currency'][$currency])) {
                $monthsMap[$month]['totals_by_currency'][$currency] = 0.0;
            }

            $monthsMap[$month]['totals_by_currency'][$currency] += $commission;
            $monthsMap[$month]['projects'][] = [
                'id' => (int) $project->id,
                'client_name' => (string) $project->client_name,
                'project_amount' => (float) $project->total_amount,
                'currency' => $currency,
                'commission' => $commission,
                'paid_at' => $paidAt->format('d.m.Y'),
            ];
        }

        $months = collect(range(1, 12))->map(function (int $month) use ($monthsMap) {
            $entry = $monthsMap[$month];
            ksort($entry['totals_by_currency']);

            return [
                'month' => $month,
                'totals_by_currency' => collect($entry['totals_by_currency'])
                    ->map(fn ($amount, $currency) => [
                        'currency' => $currency,
                        'amount' => round((float) $amount, 2),
                    ])
                    ->values(),
                'projects' => collect($entry['projects'])->values(),
            ];
        })->values();

        return [
            'view_type' => 'manager',
            'staff_group' => 'manager',
            'staff_name' => $this->userDisplayName($manager),
            'year' => $year,
            'months' => $months,
        ];
    }

    public function settings()
    {
        return view('salary.settings');
    }

    public function settingsData()
    {
        $this->ensureDefaultRules();

        $rules = Schema::hasTable('salary_rules')
            ? SalaryRule::query()
                ->orderBy('staff_group')
                ->orderBy('staff_name')
                ->get()
            : collect();

        return response()->json([
            'subjects' => $this->subjectOptions(),
            'rules' => $rules,
        ]);
    }

    public function index(Request $request)
    {
        $this->ensureDefaultRules();

        if (!Schema::hasTable('salary_rules')) {
            return response()->json([
                'rules' => [],
            ]);
        }

        $query = SalaryRule::query()->orderBy('staff_group')->orderBy('staff_name');

        if ($request->filled('staff_group')) {
            $query->where('staff_group', $request->string('staff_group'));
        }

        if ($request->filled('staff_name')) {
            $query->where('staff_name', trim((string) $request->input('staff_name')));
        }

        return response()->json([
            'rules' => $query->get(),
        ]);
    }

    public function upsert(Request $request)
    {
        if (!Schema::hasTable('salary_rules')) {
            return response()->json(['error' => 'Таблиця правил зарплатні ще не створена. Запустіть міграції.'], 422);
        }

        $data = $request->validate([
            'staff_group' => 'required|in:electrician,installation_team,manager,accountant,foreman',
            'staff_name' => 'required|string|max:255',
            'mode' => 'required|in:fixed,piecework',
            'currency' => 'required|in:UAH,USD,EUR',
            'fixed_amount' => 'nullable|numeric|min:0',
            'piecework_unit_rate' => 'nullable|numeric|min:0',
            'foreman_bonus' => 'nullable|numeric|min:0',
            'piecework_grid_le_50' => 'nullable|numeric|min:0',
            'piecework_grid_gt_50' => 'nullable|numeric|min:0',
            'piecework_hybrid_le_50' => 'nullable|numeric|min:0',
            'piecework_hybrid_gt_50' => 'nullable|numeric|min:0',
        ]);

        $staffName = trim((string) $data['staff_name']);
        if ($staffName === '') {
            return response()->json(['error' => 'Вкажіть працівника'], 422);
        }

        if (
            $data['staff_group'] === 'installation_team'
            && $data['mode'] === 'piecework'
            && !$this->hasInstallerRateColumns()
        ) {
            return response()->json(['error' => 'Для правил монтажників потрібно виконати міграції.'], 422);
        }

        $payload = [
            'mode' => $data['mode'],
            'currency' => $data['currency'],
            'fixed_amount' => $data['mode'] === 'fixed' ? $data['fixed_amount'] : null,
            'piecework_grid_le_50' => $data['mode'] === 'piecework' && $data['staff_group'] === 'electrician' ? $data['piecework_grid_le_50'] : null,
            'piecework_grid_gt_50' => $data['mode'] === 'piecework' && $data['staff_group'] === 'electrician' ? $data['piecework_grid_gt_50'] : null,
            'piecework_hybrid_le_50' => $data['mode'] === 'piecework' && $data['staff_group'] === 'electrician' ? $data['piecework_hybrid_le_50'] : null,
            'piecework_hybrid_gt_50' => $data['mode'] === 'piecework' && $data['staff_group'] === 'electrician' ? $data['piecework_hybrid_gt_50'] : null,
            'created_by' => auth()->id(),
        ];

        if ($this->hasInstallerRateColumns()) {
            $payload['piecework_unit_rate'] = $data['mode'] === 'piecework' && $data['staff_group'] === 'installation_team'
                ? $data['piecework_unit_rate']
                : null;
            $payload['foreman_bonus'] = $data['mode'] === 'piecework' && $data['staff_group'] === 'installation_team'
                ? $data['foreman_bonus']
                : null;
        }

        if ($data['mode'] === 'fixed' && $payload['fixed_amount'] === null) {
            return response()->json(['error' => 'Для ставки вкажіть суму'], 422);
        }

        if ($data['mode'] === 'piecework' && $data['staff_group'] === 'electrician') {
            $hasAnyPieceworkValue = collect([
                $payload['piecework_grid_le_50'],
                $payload['piecework_grid_gt_50'],
                $payload['piecework_hybrid_le_50'],
                $payload['piecework_hybrid_gt_50'],
            ])->contains(fn ($value) => $value !== null);

            if (!$hasAnyPieceworkValue) {
                return response()->json(['error' => 'Для виробітку задайте хоча б одне правило'], 422);
            }
        }

        if (
            $data['mode'] === 'piecework'
            && $data['staff_group'] === 'installation_team'
            && empty($payload['piecework_unit_rate'])
        ) {
            return response()->json(['error' => 'Для монтажників вкажіть ставку за 1 кВт'], 422);
        }

        SalaryRule::query()->updateOrCreate(
            [
                'staff_group' => $data['staff_group'],
                'staff_name' => $staffName,
            ],
            $payload
        );

        return response()->json(['ok' => true]);
    }

    public function managerPayoutData(Request $request)
    {
        $data = $request->validate([
            'year' => 'required|integer|min:2026|max:2100',
            'month' => 'required|integer|min:1|max:12',
        ]);

        $year = (int) $data['year'];
        $month = (int) $data['month'];

        $managers = User::query()
            ->where('role', 'ntv')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $managerIds = $managers->pluck('id')->all();

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

        $projects = SalesProject::query()
            ->whereIn('created_by', $managerIds ?: [0])
            ->orderByDesc('id')
            ->get();

        $projectsByManager = [];

        foreach ($projects as $project) {
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

            $managerId = (int) $project->created_by;
            $currency = (string) $project->currency;
            $commission = round((float) $project->total_amount * 0.01, 2);

            if (!isset($projectsByManager[$managerId])) {
                $projectsByManager[$managerId] = [
                    'totals_by_currency' => [],
                    'projects' => [],
                ];
            }

            if (!isset($projectsByManager[$managerId]['totals_by_currency'][$currency])) {
                $projectsByManager[$managerId]['totals_by_currency'][$currency] = 0.0;
            }

            $projectsByManager[$managerId]['totals_by_currency'][$currency] += $commission;
            $projectsByManager[$managerId]['projects'][] = [
                'id' => $project->id,
                'client_name' => $project->client_name,
                'project_amount' => (float) $project->total_amount,
                'currency' => $currency,
                'commission' => $commission,
                'paid_at' => $paidAt->format('d.m.Y'),
            ];
        }

        $result = $managers->map(function ($manager) use ($projectsByManager) {
            $entry = $projectsByManager[$manager->id] ?? [
                'totals_by_currency' => [],
                'projects' => [],
            ];

            ksort($entry['totals_by_currency']);

            return [
                'id' => (int) $manager->id,
                'name' => $manager->name ?: ($manager->email ?: ('Менеджер #' . $manager->id)),
                'totals_by_currency' => collect($entry['totals_by_currency'])
                    ->map(fn ($amount, $currency) => [
                        'currency' => $currency,
                        'amount' => round((float) $amount, 2),
                    ])
                    ->values(),
                'projects' => collect($entry['projects'])->values(),
            ];
        })->values();

        return response()->json([
            'year' => $year,
            'month' => $month,
            'managers' => $result,
        ]);
    }

    public function fixedEmployeeData(Request $request)
    {
        $data = $request->validate([
            'staff_group' => 'required|in:electrician,installation_team,manager,accountant,foreman',
            'staff_name' => 'required|string|max:255',
            'year' => 'required|integer|min:2026|max:2100',
        ]);

        $staffGroup = (string) $data['staff_group'];
        $staffName = trim((string) $data['staff_name']);
        $year = (int) $data['year'];

        return response()->json($this->buildFixedEmployeePayload($staffGroup, $staffName, $year));
    }

    public function myForemanFixedSalaryData(Request $request)
    {
        $user = $request->user();
        if (!$user || $user->role !== 'worker' || $user->position !== 'foreman') {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'year' => 'required|integer|min:2026|max:2100',
        ]);

        $staffName = trim((string) ($user->name ?? ''));
        if ($staffName === '') {
            $staffName = trim((string) ($user->actor ?? ''));
        }
        if ($staffName === '') {
            $staffName = trim((string) ($user->email ?? ''));
        }

        return response()->json($this->buildFixedEmployeePayload('foreman', $staffName, (int) $data['year']));
    }

    public function mySalaryData(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        if (in_array($user->role, ['sunfix', 'sunfix_manager', 'owner'], true)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'year' => 'required|integer|min:2026|max:2100',
        ]);

        $year = (int) $data['year'];
        $staffName = $this->userDisplayName($user);

        if ($user->role === 'ntv') {
            return response()->json($this->buildManagerYearPayload($user, $year));
        }

        if ($user->role === 'accountant') {
            return response()->json($this->buildFixedEmployeePayload('accountant', $staffName, $year));
        }

        if ($user->role === 'worker' && $user->position === 'foreman') {
            return response()->json($this->buildFixedEmployeePayload('foreman', $staffName, $year));
        }

        if ($user->role === 'worker' && $user->position === 'electrician') {
            $rule = Schema::hasTable('salary_rules')
                ? SalaryRule::query()
                    ->where('staff_group', 'electrician')
                    ->where('staff_name', $staffName)
                    ->first()
                : null;

            if ($rule && (string) $rule->mode === 'fixed') {
                return response()->json($this->buildFixedEmployeePayload('electrician', $staffName, $year));
            }

            return response()->json([
                'view_type' => 'unsupported',
                'message' => 'Для вашого профілю використовується виробіток. Персональний read-only екран для цього режиму ще не налаштований.',
            ]);
        }

        return response()->json([
            'view_type' => 'unsupported',
            'message' => 'Для вашої ролі персональна зарплатня ще не налаштована.',
        ]);
    }

    public function saveFixedEmployeePenalties(Request $request)
    {
        if (!$this->supportsSalaryPenalties()) {
            return response()->json(['error' => 'Таблиця штрафів ще не створена. Запустіть міграції.'], 422);
        }

        $data = $request->validate([
            'staff_group' => 'required|in:electrician,installation_team,manager,accountant,foreman',
            'staff_name' => 'required|string|max:255',
            'year' => 'required|integer|min:2026|max:2100',
            'month' => 'required|integer|min:1|max:12',
            'bonuses' => 'array',
            'bonuses.*.amount' => 'nullable|numeric|min:0',
            'bonuses.*.description' => 'nullable|string|max:255',
            'penalties' => 'array',
            'penalties.*.amount' => 'nullable|numeric|min:0',
            'penalties.*.description' => 'nullable|string|max:255',
        ]);

        $staffGroup = (string) $data['staff_group'];
        $staffName = trim((string) $data['staff_name']);
        $year = (int) $data['year'];
        $month = (int) $data['month'];

        $bonusRows = collect($data['bonuses'] ?? [])
            ->map(function ($item, $index) {
                $amount = round((float) ($item['amount'] ?? 0), 2);
                $description = trim((string) ($item['description'] ?? ''));

                return [
                    'entry_type' => 'bonus',
                    'amount' => $amount,
                    'description' => $description,
                    'sort_order' => $index,
                ];
            })
            ->filter(function ($item) {
                return $item['amount'] > 0 || $item['description'] !== '';
            })
            ->values();

        $penaltyRows = collect($data['penalties'] ?? [])
            ->map(function ($item, $index) {
                $amount = round((float) ($item['amount'] ?? 0), 2);
                $description = trim((string) ($item['description'] ?? ''));

                return [
                    'entry_type' => 'penalty',
                    'amount' => $amount,
                    'description' => $description,
                    'sort_order' => $index,
                ];
            })
            ->filter(function ($item) {
                return $item['amount'] > 0 || $item['description'] !== '';
            })
            ->values();

        $rows = $bonusRows->concat($penaltyRows)->values();

        DB::transaction(function () use ($staffGroup, $staffName, $year, $month, $rows) {
            SalaryPenalty::query()
                ->where('staff_group', $staffGroup)
                ->where('staff_name', $staffName)
                ->where('year', $year)
                ->where('month', $month)
                ->delete();

            if ($rows->isEmpty()) {
                return;
            }

            $now = now();
            SalaryPenalty::insert($rows->map(function ($row) use ($staffGroup, $staffName, $year, $month, $now) {
                return [
                    'staff_group' => $staffGroup,
                    'staff_name' => $staffName,
                    'entry_type' => $row['entry_type'],
                    'year' => $year,
                    'month' => $month,
                    'amount' => $row['amount'],
                    'description' => $row['description'] !== '' ? $row['description'] : null,
                    'sort_order' => $row['sort_order'],
                    'created_by' => auth()->id(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            })->all());
        });

        $bonusTotal = round((float) $bonusRows->sum('amount'), 2);
        $penaltyTotal = round((float) $rows->sum('amount'), 2);

        return response()->json([
            'ok' => true,
            'bonus_total' => $bonusTotal,
            'penalty_total' => round((float) $penaltyRows->sum('amount'), 2),
        ]);
    }
}
