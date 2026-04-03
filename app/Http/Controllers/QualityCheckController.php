<?php

namespace App\Http\Controllers;

use App\Models\QualityCheck;
use App\Models\QualityPhoto;
use App\Models\SalaryAccrual;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class QualityCheckController extends Controller
{
    // ── Helpers ──────────────────────────────────────────────────────────────

    private function isInstaller($user): bool
    {
        return $user->role === 'worker'
            && in_array(mb_strtolower((string) $user->actor), ['kryzhanovskyi', 'kukuiaka', 'shevchenko', 'samoilenko'], true);
    }

    private function isElectrician($user): bool
    {
        return $user->role === 'worker' && $user->position === 'electrician';
    }

    private function isForeman($user): bool
    {
        return ($user->role === 'worker' && $user->position === 'foreman')
            || $user->role === 'owner';
    }

    /**
     * Calculate salary for one worker on a project.
     * Returns ['amount' => float, 'currency' => string, 'details' => string]
     */
    private function calcSalary(object $project, string $staffGroup, string $staffName): array
    {
        $rule = DB::table('salary_rules')
            ->where('staff_group', $staffGroup)
            ->where('staff_name', $staffName)
            ->first();

        if (!$rule) {
            return ['amount' => 0, 'currency' => 'USD', 'details' => 'Правило не знайдено'];
        }

        if ($rule->mode === 'fixed') {
            return [
                'amount'   => (float) $rule->fixed_amount,
                'currency' => $rule->currency,
                'details'  => 'Фіксована ставка',
            ];
        }

        // Piecework — installation_team
        if ($staffGroup === 'installation_team') {
            $panelName = (string) ($project->panel_name ?? '');
            $panelQty  = (int) ($project->panel_qty ?? 0);

            $watts = 0;
            if (preg_match('/(\d+(?:[.,]\d+)?)\s*(?:w|wp|вт)/iu', $panelName, $m)) {
                $watts = (float) str_replace(',', '.', $m[1]);
            } elseif (preg_match('/(\d{3,4})/', $panelName, $m)) {
                $watts = (float) $m[1]; // fallback: bare number like "625"
            }

            $totalKw = $watts && $panelQty ? (int) ceil(($watts * $panelQty) / 1000) : 0;
            $amount  = ($totalKw * (float) $rule->piecework_unit_rate) + (float) $rule->foreman_bonus;

            return [
                'amount'   => $amount,
                'currency' => $rule->currency,
                'details'  => $totalKw ? "{$totalKw} кВт × {$rule->piecework_unit_rate}" : 'Панелі не вказані',
            ];
        }

        // Piecework — electrician
        if ($staffGroup === 'electrician') {
            $inverter = (string) ($project->inverter ?? '');

            $kw = 0;
            if (preg_match('/(\d+(?:[.,]\d+)?)\s*(?:k|kw|квт)/iu', $inverter, $m)) {
                $kw = (float) str_replace(',', '.', $m[1]);
            }

            $isHybrid = (bool) preg_match('/hybrid|гібрид|гибрид|hyb\b|neo\b|lite\b/iu', $inverter);

            if ($isHybrid) {
                $rate = $kw <= 50
                    ? (float) $rule->piecework_hybrid_le_50
                    : (float) $rule->piecework_hybrid_gt_50;
            } else {
                $rate = $kw <= 50
                    ? (float) $rule->piecework_grid_le_50
                    : (float) $rule->piecework_grid_gt_50;
            }

            $amount = $kw * $rate;

            return [
                'amount'   => $amount,
                'currency' => $rule->currency,
                'details'  => $kw ? "{$kw} кВт" : 'Інвертор не розпізнано',
            ];
        }

        return ['amount' => 0, 'currency' => $rule->currency ?? 'USD', 'details' => ''];
    }

    /**
     * Accrue salary for every worker assigned to the project.
     * Skips workers without a matched user account (logs warning).
     */
    private function accrueForProject(int $projectId): void
    {
        $project = DB::table('sales_projects')->where('id', $projectId)->first();
        if (!$project) return;

        $workers = []; // [ [user_id, staff_group, staff_name] ]

        // Helper: find user_id via salary_rules (has user_id FK set by admin)
        $findUserId = function (string $staffGroup, string $staffName): ?int {
            $rule = DB::table('salary_rules')
                ->where('staff_group', $staffGroup)
                ->where('staff_name', $staffName)
                ->whereNotNull('user_id')
                ->first();
            return $rule ? (int) $rule->user_id : null;
        };

        // Installation team
        $team = trim((string) ($project->installation_team ?? ''));
        if ($team && $team !== 'Без монтажних робіт') {
            $userId = $findUserId('installation_team', $team);
            if ($userId) {
                $workers[] = ['user_id' => $userId, 'staff_group' => 'installation_team', 'staff_name' => $team];
            } else {
                Log::warning("quality-check accrual: no user found for installation_team '{$team}'");
            }
        }

        // Electrician
        $electrician = trim((string) ($project->electrician ?? ''));
        if ($electrician && $electrician !== 'Без монтажних робіт') {
            $userId = $findUserId('electrician', $electrician);
            if ($userId) {
                $workers[] = ['user_id' => $userId, 'staff_group' => 'electrician', 'staff_name' => $electrician];
            } else {
                Log::warning("quality-check accrual: no user found for electrician '{$electrician}'");
            }
        }

        foreach ($workers as $w) {
            // Skip if already accrued for this project+user
            $exists = DB::table('salary_accruals')
                ->where('project_id', $projectId)
                ->where('user_id', $w['user_id'])
                ->exists();
            if ($exists) continue;

            $salary = $this->calcSalary($project, $w['staff_group'], $w['staff_name']);

            DB::table('salary_accruals')->insert([
                'project_id'  => $projectId,
                'user_id'     => $w['user_id'],
                'staff_group' => $w['staff_group'],
                'staff_name'  => $w['staff_name'],
                'amount'      => $salary['amount'],
                'currency'    => $salary['currency'],
                'details'     => $salary['details'],
                'status'      => 'pending',
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }
    }

    // ── Actions ───────────────────────────────────────────────────────────────

    /**
     * POST /api/projects/{id}/complete-construction
     * Worker marks project as done → waiting quality check
     */
    public function completeConstruction(Request $request, int $id): JsonResponse
    {
        $user = auth()->user();

        if (!$this->isInstaller($user) && !$this->isElectrician($user) && $user->role !== 'owner') {
            return response()->json(['error' => 'Немає доступу'], 403);
        }

        $project = DB::table('sales_projects')->where('id', $id)->first();
        if (!$project) {
            return response()->json(['error' => 'Проект не знайдено'], 404);
        }

        if ($project->construction_status === 'waiting_quality_check') {
            return response()->json(['error' => 'Вже очікує перевірки'], 422);
        }

        // Заборона завершувати майбутні проекти (тільки сьогодні або минулі)
        if ($user->role !== 'owner') {
            $startDate = $project->panel_work_start_date ?? null;
            if ($startDate) {
                $start = \Carbon\Carbon::parse($startDate)->startOfDay();
                $today = \Carbon\Carbon::today();
                if ($start->gt($today)) {
                    return response()->json(['error' => 'Не можна завершити майбутній проект. Завершення доступне тільки сьогодні або для минулих дат.'], 422);
                }
            }
        }

        $completedAt = now();

        DB::transaction(function () use ($id, $user, $completedAt) {
            DB::table('sales_projects')->where('id', $id)->update([
                'construction_status'       => 'waiting_quality_check',
                'installation_completed_at' => $completedAt,
                'installation_completed_by' => $user->name,
                'updated_at'                => $completedAt,
            ]);

            // Remove any old pending check for this project before creating new one
            DB::table('quality_checks')
                ->where('project_id', $id)
                ->where('status', 'pending')
                ->delete();

            DB::table('quality_checks')->insert([
                'project_id' => $id,
                'created_by' => $user->id,
                'status'     => 'pending',
                'created_at' => $completedAt,
                'updated_at' => $completedAt,
            ]);
        });

        // Notify foremen (owner + manager roles)
        $project = DB::table('sales_projects')->where('id', $id)->first();
        $dateLabel = $completedAt->format('d.m.Y');
        $notifTitle = '🔧 Монтаж завершено';
        $notifBody  = "🔧 Монтаж завершено\n\n"
            . "👷 Монтажник: {$user->name}\n"
            . "📍 Проект: {$project->client_name}\n"
            . "📅 Дата завершення: {$dateLabel}\n\n"
            . 'Потрібно перевірити якість робіт і підтвердити завершення для нарахування зарплати.';

        $notifService = app(\App\Services\NotificationService::class);
        $recipients = DB::table('users')
            ->where(function ($q) {
                $q->whereIn('role', ['owner', 'manager'])
                  ->orWhere('actor', 'foreman');
            })
            ->get();
        foreach ($recipients as $recipient) {
            $notifService->send((int) $recipient->id, $notifTitle, $notifBody, 'system');
        }

        return response()->json([
            'ok'                        => true,
            'construction_status'       => 'waiting_quality_check',
            'installation_completed_at' => $completedAt->toISOString(),
        ]);
    }

    /**
     * GET /quality-checks
     * List of projects waiting for quality check (foreman/owner)
     */
    public function index()
    {
        $user = auth()->user();
        if (!$this->isForeman($user)) {
            abort(403);
        }

        return view('quality-checks.index');
    }

    /**
     * GET /api/quality-checks
     * JSON list for the quality-checks view
     */
    public function apiIndex(): JsonResponse
    {
        $user = auth()->user();
        if (!$this->isForeman($user)) {
            return response()->json(['error' => 'Немає доступу'], 403);
        }

        $checks = DB::table('quality_checks as qc')
            ->join('sales_projects as sp', 'sp.id', '=', 'qc.project_id')
            ->join('users as u', 'u.id', '=', 'qc.created_by')
            ->whereIn('qc.status', ['pending', 'has_deficiencies', 'deficiencies_fixed'])
            ->select([
                'qc.id',
                'qc.project_id',
                'qc.status as check_status',
                'qc.deficiencies',
                'qc.voice_memo_path',
                'qc.created_at',
                'sp.client_name',
                'sp.installation_team',
                'sp.electrician',
                'sp.panel_name',
                'sp.panel_qty',
                'sp.inverter',
                'sp.construction_status',
                'u.name as submitted_by',
            ])
            ->orderBy('qc.created_at')
            ->get();

        // Attach photos for each check
        $checkIds = $checks->pluck('id')->all();
        $photosByCheck = DB::table('quality_photos')
            ->whereIn('quality_check_id', $checkIds)
            ->select('quality_check_id', 'file_path')
            ->get()
            ->groupBy('quality_check_id');

        $checks = $checks->map(function ($check) use ($photosByCheck) {
            $check->photos = ($photosByCheck->get($check->id) ?? collect())
                ->map(fn ($p) => Storage::disk('public')->url($p->file_path))
                ->values()
                ->all();
            $check->voice_memo_url = $check->voice_memo_path
                ? Storage::disk('public')->url($check->voice_memo_path)
                : null;
            return $check;
        });

        return response()->json($checks->values());
    }

    /**
     * POST /api/quality-checks/{id}/approve
     * Foreman approves project: saves deficiencies + photos, accrues salary
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $user = auth()->user();
        if (!$this->isForeman($user)) {
            return response()->json(['error' => 'Немає доступу'], 403);
        }

        $check = DB::table('quality_checks')->where('id', $id)->first();
        if (!$check) {
            return response()->json(['error' => 'Перевірку не знайдено'], 404);
        }
        if ($check->status === 'approved') {
            return response()->json(['error' => 'Вже прийнято'], 422);
        }
        if ($check->status === 'has_deficiencies') {
            return response()->json(['error' => 'Спочатку потрібно виправити недоліки'], 422);
        }

        $deficiencies = trim((string) $request->input('deficiencies', ''));

        DB::transaction(function () use ($id, $check, $deficiencies, $user, $request) {
            // Update quality check
            DB::table('quality_checks')->where('id', $id)->update([
                'status'       => 'approved',
                'deficiencies' => $deficiencies ?: null,
                'approved_by'  => $user->id,
                'approved_at'  => now(),
                'updated_at'   => now(),
            ]);

            // Save photos
            if ($request->hasFile('photos')) {
                foreach ($request->file('photos') as $photo) {
                    $path = $photo->store('quality-photos', 'public');
                    DB::table('quality_photos')->insert([
                        'quality_check_id' => $id,
                        'file_path'        => $path,
                        'created_at'       => now(),
                        'updated_at'       => now(),
                    ]);
                }
            }

            // Also save deficiencies to the existing defects_note field on project
            $projectUpdate = ['construction_status' => 'quality_approved', 'updated_at' => now()];
            if ($deficiencies) {
                $projectUpdate['defects_note'] = $deficiencies;
            }
            DB::table('sales_projects')->where('id', $check->project_id)->update($projectUpdate);

            // Accrue salary for workers on this project
            $this->accrueForProject($check->project_id);

            // Update project status to salary_pending
            DB::table('sales_projects')->where('id', $check->project_id)->update([
                'construction_status' => 'salary_pending',
                'updated_at'          => now(),
            ]);
        });

        return response()->json(['ok' => true]);
    }

    /**
     * POST /api/quality-checks/{id}/cancel
     * Foreman/owner cancels quality check → reverts project to in_progress
     */
    public function cancelCheck(Request $request, int $id): JsonResponse
    {
        $user = auth()->user();
        if (!$this->isForeman($user)) {
            return response()->json(['error' => 'Немає доступу'], 403);
        }

        $check = DB::table('quality_checks')->where('id', $id)->first();
        if (!$check) {
            return response()->json(['error' => 'Перевірку не знайдено'], 404);
        }
        if ($check->status === 'approved') {
            return response()->json(['error' => 'Вже затверджено, скасування неможливе'], 422);
        }

        DB::transaction(function () use ($id, $check) {
            DB::table('quality_checks')->where('id', $id)->delete();

            DB::table('sales_projects')->where('id', $check->project_id)->update([
                'construction_status'       => 'in_progress',
                'installation_completed_at' => null,
                'installation_completed_by' => null,
                'updated_at'                => now(),
            ]);
        });

        return response()->json(['ok' => true]);
    }

    /**
     * POST /api/quality-checks/{id}/save-deficiencies
     * Foreman sends deficiencies to worker without approving
     */
    public function saveDeficiencies(Request $request, int $id): JsonResponse
    {
        $user = auth()->user();
        if (!$this->isForeman($user)) {
            return response()->json(['error' => 'Немає доступу'], 403);
        }

        $check = DB::table('quality_checks')->where('id', $id)->first();
        if (!$check) {
            return response()->json(['error' => 'Перевірку не знайдено'], 404);
        }
        if (!in_array($check->status, ['pending', 'has_deficiencies', 'deficiencies_fixed'], true)) {
            return response()->json(['error' => 'Неможливо відправити в цьому статусі'], 422);
        }

        $deficiencies = trim((string) $request->input('deficiencies', ''));

        // Store files before transaction (avoids issues with streams inside closure)
        $photoPaths = [];
        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $photo) {
                $photoPaths[] = $photo->store('quality-photos', 'public');
            }
        }
        $voicePath = null;
        if ($request->hasFile('voice_memo')) {
            $voicePath = $request->file('voice_memo')->store('quality-voice', 'public');
        }

        DB::transaction(function () use ($id, $check, $deficiencies, $photoPaths, $voicePath) {
            $qcUpdate = [
                'status'       => 'has_deficiencies',
                'deficiencies' => $deficiencies ?: null,
                'updated_at'   => now(),
            ];
            if ($voicePath) {
                $qcUpdate['voice_memo_path'] = $voicePath;
            }
            DB::table('quality_checks')->where('id', $id)->update($qcUpdate);

            foreach ($photoPaths as $path) {
                DB::table('quality_photos')->insert([
                    'quality_check_id' => $id,
                    'file_path'        => $path,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]);
            }

            $projectUpdate = [
                'construction_status' => 'has_deficiencies',
                'updated_at'          => now(),
            ];
            if ($deficiencies) {
                $projectUpdate['defects_note'] = $deficiencies;
            }
            DB::table('sales_projects')->where('id', $check->project_id)->update($projectUpdate);
        });

        // Notify installers on this project
        $project = DB::table('sales_projects')->where('id', $check->project_id)->first();
        if ($project && $deficiencies) {
            $actorMap = [
                'kryzhanovskyi' => 'Крижановський',
                'kukuiaka'      => 'Кукуяка',
                'shevchenko'    => 'Шевченко',
                'samoilenko'    => 'Самойленко',
            ];
            $teamName = mb_strtolower(trim((string) ($project->installation_team ?? '')));
            $notifService = app(\App\Services\NotificationService::class);
            foreach ($actorMap as $actor => $ukName) {
                if (mb_strtolower($ukName) === $teamName) {
                    $installer = DB::table('users')->where('actor', $actor)->first();
                    if ($installer) {
                        $body = "⚠️ Виявлено недоліки\n\n"
                            . "📍 Проект: {$project->client_name}\n"
                            . "📝 Коментар: {$deficiencies}\n\n"
                            . "Потрібно виправити та повторно завершити.";
                        $notifService->send((int) $installer->id, '⚠️ Виявлено недоліки', $body, 'system');
                    }
                    break;
                }
            }
        }

        return response()->json(['ok' => true]);
    }

    /**
     * POST /api/projects/{id}/deficiencies-fixed
     * Worker marks deficiencies as fixed
     */
    public function markFixed(Request $request, int $id): JsonResponse
    {
        $user = auth()->user();
        if (!$this->isInstaller($user) && !$this->isElectrician($user) && $user->role !== 'owner') {
            return response()->json(['error' => 'Немає доступу'], 403);
        }

        $project = DB::table('sales_projects')->where('id', $id)->first();
        if (!$project) {
            return response()->json(['error' => 'Проект не знайдено'], 404);
        }

        $check = DB::table('quality_checks')
            ->where('project_id', $id)
            ->where('status', 'has_deficiencies')
            ->first();

        if (!$check) {
            return response()->json(['error' => 'Немає відкритих недоліків'], 422);
        }

        DB::transaction(function () use ($id, $check) {
            DB::table('quality_checks')->where('id', $check->id)->update([
                'status'     => 'deficiencies_fixed',
                'updated_at' => now(),
            ]);
            DB::table('sales_projects')->where('id', $id)->update([
                'construction_status' => 'waiting_quality_check',
                'updated_at'          => now(),
            ]);
        });

        $project = DB::table('sales_projects')->where('id', $id)->first();
        if ($project) {
            $notifService = app(\App\Services\NotificationService::class);
            $foremen = DB::table('users')->whereIn('role', ['owner', 'manager'])->get();
            foreach ($foremen as $foreman) {
                $notifService->send(
                    (int) $foreman->id,
                    '🔧 Недоліки виправлено',
                    "🔧 Недоліки виправлено\n\n📍 Проект: {$project->client_name}\n\nПотрібно повторно перевірити.",
                    'system'
                );
            }
        }

        return response()->json(['ok' => true, 'construction_status' => 'waiting_quality_check']);
    }

    /**
     * GET /salary/accruals
     * Owner sees pending salary accruals grouped by worker
     */
    public function accruals()
    {
        if (auth()->user()->role !== 'owner') {
            abort(403);
        }

        return view('salary.accruals');
    }

    /**
     * GET /api/salary/accruals
     * JSON: pending accruals grouped by user
     */
    public function apiAccruals(): JsonResponse
    {
        if (auth()->user()->role !== 'owner') {
            return response()->json(['error' => 'Немає доступу'], 403);
        }

        $rows = DB::table('salary_accruals as sa')
            ->join('users as u', 'u.id', '=', 'sa.user_id')
            ->join('sales_projects as sp', 'sp.id', '=', 'sa.project_id')
            ->where('sa.status', 'pending')
            ->select([
                'sa.id',
                'sa.project_id',
                'sa.user_id',
                'sa.staff_group',
                'sa.staff_name',
                'sa.amount',
                'sa.currency',
                'sa.details',
                'sa.created_at',
                'u.name as user_name',
                'sp.client_name',
                'sp.construction_status',
            ])
            ->orderBy('u.name')
            ->orderBy('sa.created_at')
            ->get();

        // Group by user_id
        $grouped = [];
        foreach ($rows as $row) {
            $uid = $row->user_id;
            if (!isset($grouped[$uid])) {
                $grouped[$uid] = [
                    'user_id'   => $uid,
                    'user_name' => $row->user_name,
                    'currency'  => $row->currency,
                    'total'     => 0,
                    'accruals'  => [],
                ];
            }
            $grouped[$uid]['total']     += $row->amount;
            $grouped[$uid]['accruals'][] = $row;
        }

        return response()->json(array_values($grouped));
    }

    /**
     * GET /api/salary/accruals/paid
     * JSON: paid accruals grouped by user
     */
    public function apiPaidAccruals(): JsonResponse
    {
        if (auth()->user()->role !== 'owner') {
            return response()->json(['error' => 'Немає доступу'], 403);
        }

        $rows = DB::table('salary_accruals as sa')
            ->join('users as u', 'u.id', '=', 'sa.user_id')
            ->join('sales_projects as sp', 'sp.id', '=', 'sa.project_id')
            ->where('sa.status', 'paid')
            ->select([
                'sa.id',
                'sa.project_id',
                'sa.user_id',
                'sa.staff_group',
                'sa.staff_name',
                'sa.amount',
                'sa.currency',
                'sa.details',
                'sa.created_at',
                'sa.paid_at',
                'sa.paid_usd',
                'sa.paid_uah',
                'sa.paid_rate',
                'u.name as user_name',
                'sp.client_name',
            ])
            ->orderBy('u.name')
            ->orderByDesc('sa.paid_at')
            ->get();

        $grouped = [];
        foreach ($rows as $row) {
            $uid = $row->user_id;
            if (!isset($grouped[$uid])) {
                $grouped[$uid] = [
                    'user_id'   => $uid,
                    'user_name' => $row->user_name,
                    'currency'  => $row->currency,
                    'accruals'  => [],
                ];
            }
            $grouped[$uid]['accruals'][] = $row;
        }

        return response()->json(array_values($grouped));
    }

    /**
     * POST /api/salary/pay/{userId}
     *
     * Legacy mode  (wallet_id present): single-wallet, single-currency — unchanged behaviour.
     * Multi-currency mode (usd_wallet_id present): supports partial USD payout + UAH remainder
     * + optional bonus in USD or UAH.  Two separate expense entries are created (NOT wrapped
     * in one transaction per spec).
     */
    public function paySalary(Request $request, int $userId): JsonResponse
    {
        if (auth()->user()->role !== 'owner') {
            return response()->json(['error' => 'Немає доступу'], 403);
        }

        $worker = DB::table('users')->where('id', $userId)->first();
        if (!$worker) {
            return response()->json(['error' => 'Користувача не знайдено'], 404);
        }

        $pending = DB::table('salary_accruals')
            ->where('user_id', $userId)
            ->where('status', 'pending')
            ->get();

        if ($pending->isEmpty()) {
            return response()->json(['error' => 'Немає нарахувань до виплати'], 422);
        }

        // ── Multi-currency mode ───────────────────────────────────────────────
        if ($request->has('usd_wallet_id')) {
            return $this->paySalaryMultiCurrency($request, $userId, $pending);
        }

        // ── Legacy single-wallet mode ─────────────────────────────────────────
        $byCurrency = $pending->groupBy('currency');

        $walletId = (int) $request->input('wallet_id');
        if (!$walletId) {
            return response()->json(['error' => 'Не вказано гаманець'], 422);
        }

        $wallet = DB::table('wallets')->where('id', $walletId)->where('is_active', true)->first();
        if (!$wallet) {
            return response()->json(['error' => 'Гаманець не знайдено'], 422);
        }

        DB::transaction(function () use ($pending, $byCurrency, $worker, $wallet, $userId) {
            $owner   = auth()->user();
            $entryIds = [];

            $projectIds   = $pending->pluck('project_id')->unique()->all();
            $clientNames  = DB::table('sales_projects')
                ->whereIn('id', $projectIds)
                ->pluck('client_name', 'id');

            foreach ($byCurrency as $currency => $accruals) {
                $total = $accruals->sum('amount');
                if ($total <= 0) continue;

                $staffName = $accruals->first()->staff_name;
                $clients = $accruals
                    ->pluck('project_id')
                    ->unique()
                    ->map(fn ($pid) => $clientNames->get($pid, ''))
                    ->filter()
                    ->implode(', ');
                $comment = "З/П {$staffName}" . ($clients ? " - {$clients}" : '');

                $entryId = DB::table('entries')->insertGetId([
                    'wallet_id'    => $wallet->id,
                    'posting_date' => now()->toDateString(),
                    'entry_type'   => 'expense',
                    'amount'       => $total,
                    'title'        => $comment,
                    'comment'      => $comment,
                    'created_by'   => $owner->name,
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);

                $entryIds[] = $entryId;

                $ids = $accruals->pluck('id')->all();
                DB::table('salary_accruals')->whereIn('id', $ids)->update([
                    'status'     => 'paid',
                    'paid_by'    => $owner->id,
                    'paid_at'    => now(),
                    'entry_id'   => $entryId,
                    'updated_at' => now(),
                ]);
            }

            $projectIds = $pending->pluck('project_id')->unique()->all();
            foreach ($projectIds as $pid) {
                $stillPending = DB::table('salary_accruals')
                    ->where('project_id', $pid)
                    ->where('status', 'pending')
                    ->exists();
                if (!$stillPending) {
                    DB::table('sales_projects')->where('id', $pid)->update([
                        'construction_status' => 'salary_paid',
                        'updated_at'          => now(),
                    ]);
                }
            }
        });

        return response()->json(['ok' => true]);
    }

    /**
     * Multi-currency salary payout:
     *   salary_usd  — sum of pending USD accruals
     *   bonus_amount / bonus_currency — optional bonus (USD or UAH)
     *   usd_paid    — how much to pay in USD (rest → convert to UAH at FX rate)
     *   usd_wallet_id / uah_wallet_id — wallets for each currency
     *
     * Two separate expense entries are created (not in one transaction).
     */
    private function paySalaryMultiCurrency(
        \Illuminate\Http\Request $request,
        int $userId,
        \Illuminate\Support\Collection $pending
    ): JsonResponse {
        $owner = auth()->user();

        $usdWalletId   = (int)   $request->input('usd_wallet_id', 0);
        $uahWalletId   = (int)   $request->input('uah_wallet_id', 0);
        $bonusAmount   = max(0, (float) $request->input('bonus_amount', 0));
        $bonusCurrency = in_array($request->input('bonus_currency'), ['USD', 'UAH'], true)
            ? (string) $request->input('bonus_currency') : 'UAH';
        $fxRow = DB::table('fx_rates')->where('currency', 'USD')->first();
        $rate  = $fxRow ? (float) $fxRow->buy : (float) config('services.erpnext.fx.usd', 40);

        // Sum pending USD accruals (all installer accruals are in USD)
        $salaryUsd = (float) $pending->where('currency', 'USD')->sum('amount');

        $bonusUsd  = ($bonusCurrency === 'USD') ? $bonusAmount : 0.0;
        $bonusUah  = ($bonusCurrency === 'UAH') ? $bonusAmount : 0.0;
        $usdTotal  = $salaryUsd + $bonusUsd;

        // How much to pay in USD (default: all)
        $usdPaidInput = (float) $request->input('usd_paid', 0);
        $usdToPay = ($usdPaidInput > 0 && $usdPaidInput < $usdTotal)
            ? $usdPaidInput
            : $usdTotal;
        $usdToPay = round($usdToPay, 2);

        $usdRemaining = round($usdTotal - $usdToPay, 2);
        $uahFromUsd   = round($usdRemaining * $rate, 2);
        $uahTotal     = round($uahFromUsd + $bonusUah, 2);

        // Validate wallets
        $usdWallet = null;
        if ($usdToPay > 0) {
            if (!$usdWalletId) {
                return response()->json(['error' => 'Не вказано USD гаманець'], 422);
            }
            $usdWallet = DB::table('wallets')
                ->where('id', $usdWalletId)
                ->where('currency', 'USD')
                ->where('is_active', true)
                ->first();
            if (!$usdWallet) {
                return response()->json(['error' => 'USD гаманець не знайдено або не активний'], 422);
            }
        }

        $uahWallet = null;
        if ($uahTotal > 0) {
            if (!$uahWalletId) {
                return response()->json(['error' => 'Не вказано UAH гаманець'], 422);
            }
            $uahWallet = DB::table('wallets')
                ->where('id', $uahWalletId)
                ->where('currency', 'UAH')
                ->where('is_active', true)
                ->first();
            if (!$uahWallet) {
                return response()->json(['error' => 'UAH гаманець не знайдено або не активний'], 422);
            }
        }

        $accrualIds  = $pending->pluck('id')->all();
        $projectIds  = $pending->pluck('project_id')->unique()->all();
        $staffName   = $pending->first()->staff_name;
        $clientNames = DB::table('sales_projects')
            ->whereIn('id', $projectIds)
            ->pluck('client_name', 'id');
        $clients = collect($projectIds)
            ->map(fn ($pid) => $clientNames->get($pid, ''))
            ->filter()
            ->implode(', ');

        $now = now();
        $firstEntryId = null;

        // ── USD entry ──────────────────────────────────────────────────────────
        if ($usdToPay > 0 && $usdWallet) {
            $bonusNote = $bonusUsd > 0 ? " (вкл. премія {$bonusUsd} USD)" : '';
            $comment   = "З/П {$staffName}{$bonusNote}" . ($clients ? " - {$clients}" : '');
            $firstEntryId = DB::table('entries')->insertGetId([
                'wallet_id'    => $usdWallet->id,
                'posting_date' => $now->toDateString(),
                'entry_type'   => 'expense',
                'amount'       => $usdToPay,
                'title'        => $comment,
                'comment'      => $comment,
                'created_by'   => $owner->name,
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);
        }

        // ── UAH entry ──────────────────────────────────────────────────────────
        if ($uahTotal > 0 && $uahWallet) {
            $uahNote  = $bonusUah > 0 ? " + премія {$bonusUah} ₴" : '';
            $comment  = "З/П {$staffName}: {$usdRemaining} USD × {$rate}{$uahNote}" . ($clients ? " - {$clients}" : '');
            DB::table('entries')->insertGetId([
                'wallet_id'    => $uahWallet->id,
                'posting_date' => $now->toDateString(),
                'entry_type'   => 'expense',
                'amount'       => $uahTotal,
                'title'        => $comment,
                'comment'      => $comment,
                'created_by'   => $owner->name,
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);
        }

        // ── Mark accruals paid (store proportional actual amounts) ────────────
        // Distribute only the salary portion proportionally (bonus is a separate record).
        $salaryUsdPaid = $usdToPay - $bonusUsd; // salary part in USD
        $salaryUahPaid = $uahTotal  - $bonusUah; // salary part in UAH
        $totalAccrued  = max((float) $pending->sum('amount'), 0.0001);
        foreach ($pending as $accrual) {
            $ratio      = (float) $accrual->amount / $totalAccrued;
            $accrualUsd = round($salaryUsdPaid * $ratio, 2);
            $accrualUah = round($salaryUahPaid * $ratio, 2);
            DB::table('salary_accruals')->where('id', $accrual->id)->update([
                'status'     => 'paid',
                'paid_by'    => $owner->id,
                'paid_at'    => $now,
                'entry_id'   => $firstEntryId,
                'paid_usd'   => $accrualUsd > 0 ? $accrualUsd : null,
                'paid_uah'   => $accrualUah > 0 ? $accrualUah : null,
                'paid_rate'  => $rate,
                'updated_at' => $now,
            ]);
        }

        // ── Bonus accrual record (shows in installer salary history) ──────────
        if ($bonusAmount > 0) {
            $bonusPaidUsd = $bonusCurrency === 'USD' ? $bonusAmount : null;
            $bonusPaidUah = $bonusCurrency === 'UAH' ? $bonusAmount : null;
            DB::table('salary_accruals')->insert([
                'project_id'  => $pending->first()->project_id,
                'user_id'     => $userId,
                'staff_group' => $pending->first()->staff_group,
                'staff_name'  => $staffName,
                'amount'      => $bonusAmount,
                'currency'    => $bonusCurrency,
                'details'     => 'Премія',
                'status'      => 'paid',
                'paid_by'     => $owner->id,
                'paid_at'     => $now,
                'entry_id'    => $firstEntryId,
                'paid_usd'    => $bonusPaidUsd,
                'paid_uah'    => $bonusPaidUah,
                'paid_rate'   => $bonusCurrency === 'UAH' ? $rate : null,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        }

        // ── Update project statuses ────────────────────────────────────────────
        foreach ($projectIds as $pid) {
            $stillPending = DB::table('salary_accruals')
                ->where('project_id', $pid)
                ->where('status', 'pending')
                ->exists();
            if (!$stillPending) {
                DB::table('sales_projects')->where('id', $pid)->update([
                    'construction_status' => 'salary_paid',
                    'updated_at'          => $now,
                ]);
            }
        }

        return response()->json([
            'ok'       => true,
            'usd_paid' => $usdToPay,
            'uah_paid' => $uahTotal,
            'rate'     => $rate,
        ]);
    }

    /**
     * GET /api/salary/paid-history?staff_group=...&staff_name=...&year=...&month=...
     * Paid accruals for one worker, grouped by year-month for history card.
     */
    public function paidHistory(Request $request): JsonResponse
    {
        $staffGroup = trim((string) $request->input('staff_group', ''));
        $staffName  = trim((string) $request->input('staff_name', ''));
        $year       = (int) $request->input('year', 0);
        $month      = (int) $request->input('month', 0);

        $query = DB::table('salary_accruals as sa')
            ->join('sales_projects as sp', 'sp.id', '=', 'sa.project_id')
            ->where('sa.status', 'paid')
            ->select([
                'sa.id',
                'sa.project_id',
                'sa.amount',
                'sa.currency',
                'sa.paid_at',
                'sa.paid_usd',
                'sa.paid_uah',
                'sa.paid_rate',
                'sp.client_name',
                'sp.panel_name',
                'sp.panel_qty',
                'sp.inverter',
            ])
            ->orderByDesc('sa.paid_at');

        if ($staffGroup) $query->where('sa.staff_group', $staffGroup);
        if ($staffName)  $query->where('sa.staff_name', $staffName);
        if ($year > 0)   $query->whereYear('sa.paid_at', $year);
        if ($month > 0)  $query->whereMonth('sa.paid_at', $month);

        $rows = $query->get();

        // Group by year-month
        $grouped = [];
        foreach ($rows as $row) {
            $key = $row->paid_at ? substr($row->paid_at, 0, 7) : 'unknown'; // "2026-03"
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'year_month' => $key,
                    'total'      => 0,
                    'currency'   => $row->currency,
                    'rows'       => [],
                ];
            }
            $grouped[$key]['total'] += (float) $row->amount;
            $grouped[$key]['rows'][] = $row;
        }

        // Available months for selector (all time, regardless of filter)
        $allMonths = DB::table('salary_accruals')
            ->where('status', 'paid')
            ->where('staff_name', $staffName)
            ->whereNotNull('paid_at')
            ->selectRaw("strftime('%Y-%m', paid_at) as ym")
            ->distinct()
            ->orderByDesc('ym')
            ->pluck('ym');

        return response()->json([
            'groups'     => array_values($grouped),
            'all_months' => $allMonths,
        ]);
    }

    /**
     * GET /api/quality-checks/wallets
     * Return owner's active wallets for payment form
     */
    public function wallets(): JsonResponse
    {
        if (auth()->user()->role !== 'owner') {
            return response()->json(['error' => 'Немає доступу'], 403);
        }

        $wallets = DB::table('wallets')
            ->where('is_active', true)
            ->where('owner', auth()->user()->actor)
            ->select('id', 'name', 'currency', 'type')
            ->get();

        return response()->json($wallets);
    }
}
