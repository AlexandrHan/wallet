<?php

namespace App\Http\Controllers;

use App\Models\SalaryRule;
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
            ->whereIn('role', ['ntv', 'sunfix_manager'])
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
}
