<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Services\AI\AIChatRouter;
use App\Services\AI\AIAgentService;
use App\Services\AI\SqlAgentService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * AI Chat Controller — SQL-first architecture.
 *
 * Priority chain (never blocks, never throws):
 *   1. Quick SQL patterns  → instant (~5ms)
 *   2. SqlAgentService     → AI-generated SELECT (~5–30s, skipped on timeout)
 *   3. Full AI fallback    → Local/Claude with context (~30–90s, optional)
 *   4. Static fallback     → always returns something
 */
class AIChatController extends Controller
{
    /** Final fallback when everything else fails or times out */
    private const FALLBACK = 'Не вдалося знайти дані. Спробуй уточнити питання або обери одну з тем: баланс рахунків, витрати, активні проекти, склад, зарплата, недоліки.';

    public function __construct(
        private AIChatRouter    $router,
        private AIAgentService  $agent,
        private SqlAgentService $sqlAgent,
    ) {}

    // =========================================================================
    // Main handler
    // =========================================================================

    public function chat(Request $request): JsonResponse
    {
        $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'model'   => ['sometimes', 'string', 'in:local,claude'],
        ]);

        $question = trim($request->input('message'));
        $model    = $request->input('model', 'local');
        $user     = auth()->user();
        $started  = microtime(true);
        $lq       = mb_strtolower($question);

        // ── 1. Quick SQL patterns ─────────────────────────────────────────────
        try {
            $context = $this->agent->collectContext();
            $quick   = $this->quickAnswer($question, $context);
        } catch (\Throwable) {
            $context = [];
            $quick   = null;
        }

        if ($quick !== null) {
            $ms = (int) round((microtime(true) - $started) * 1000);
            $this->aiLog('quick', $question, $quick, $ms, $user?->id);
            return $this->json($quick, 'quick', $ms);
        }

        // ── 2. SQL Agent (AI generates SELECT, max 5s Ollama timeout) ────────
        // Only attempt for data-related questions to avoid wasting Ollama timeout
        $sqlResult = null;
        if ($this->looksLikeDataQuestion($lq)) {
            try {
                $sqlResult = $this->sqlAgent->handle($question, $model);
            } catch (\Throwable) {
                $sqlResult = null;
            }
        }

        if ($sqlResult !== null) {
            $ms = $sqlResult['duration_ms'];
            $this->aiLog('sql-agent', $question, $sqlResult['response'], $ms, $user?->id);
            $this->persistLog($user?->id, $question, $sqlResult['response'], 200, $ms);
            return $this->json($sqlResult['response'], 'sql-agent', $ms, $sqlResult['sql']);
        }

        // ── 3. Static fallback (instant, always works) ───────────────────────
        // AI general chat is unreliable (slow Ollama) — for ERP use SQL-first.
        // Claude fallback only if explicitly selected AND available
        if ($model === 'claude') {
            try {
                $system = 'Ти — фінансовий аналітик SolarGlass. Відповідай українською, стисло.';
                $result = $this->router->handle($question, 'claude', $context, $system);
                if (($result['status_code'] ?? 0) < 400) {
                    $ms = $result['duration_ms'];
                    $this->aiLog('claude', $question, $result['response'], $ms, $user?->id);
                    $this->persistLog($user?->id, $question, $result['response'], 200, $ms);
                    return $this->json($result['response'], 'claude', $ms);
                }
            } catch (\Throwable) {
                // Claude failed — fall through to static
            }
        }

        $ms = (int) round((microtime(true) - $started) * 1000);
        $this->aiLog('fallback', $question, self::FALLBACK, $ms, $user?->id);
        return $this->json(self::FALLBACK, 'fallback', $ms);
    }

    // =========================================================================
    // Response helper
    // =========================================================================

    private function json(string $response, string $modelUsed, int $ms, ?string $sql = null): JsonResponse
    {
        $data = ['response' => $response, 'duration_ms' => $ms, 'model_used' => $modelUsed];
        if ($sql !== null) {
            $data['sql'] = $sql;
        }
        return response()->json($data);
    }

    // =========================================================================
    // Quick SQL patterns — all DB-backed, instant, never touch AI
    // =========================================================================

    private function quickAnswer(string $q, array $context): ?string
    {
        $lq  = mb_strtolower($q);
        $orig = $q;

        // ── Wallet balances ───────────────────────────────────────────────────
        if ($this->matches($lq, ['скільки грошей', 'баланс гаманц', 'залишок гаманц', 'скільки коштів', 'гроші на рахунк', 'скільки на рахунк', 'баланс рахунк'])) {
            $balances = $context['wallet_balances'] ?? [];
            if (empty($balances)) {
                // Fallback: query directly
                $balances = $this->computeWalletBalances();
            }
            if (empty($balances)) return null;
            $lines = [];
            foreach ($balances as $currency => $amount) {
                $lines[] = "• {$currency}: " . number_format((float)$amount, 2, '.', ' ');
            }
            return "💼 **Поточні залишки по гаманцях:**\n" . implode("\n", $lines);
        }

        // ── Balance per wallet (detailed) ─────────────────────────────────────
        if ($this->matches($lq, ['баланс по рахунках', 'всі рахунки', 'список рахунків', 'які гаманці'])) {
            $rows = DB::table('wallets as w')
                ->where('w.is_active', 1)
                ->leftJoin('entries as e', 'e.wallet_id', '=', 'w.id')
                ->selectRaw("w.name, w.currency, COALESCE(SUM(CASE WHEN e.entry_type='income' THEN e.amount ELSE -e.amount END),0) as balance")
                ->groupBy('w.id', 'w.name', 'w.currency')
                ->orderByDesc('balance')
                ->get();

            if ($rows->isEmpty()) return null;
            $lines = [];
            foreach ($rows as $r) {
                $bal    = number_format((float)$r->balance, 2, '.', ' ');
                $sign   = $r->balance >= 0 ? '' : '⚠️ ';
                $lines[] = "• {$sign}{$r->name} ({$r->currency}): {$bal}";
            }
            return "💳 **Баланси всіх рахунків:**\n" . implode("\n", $lines);
        }

        // ── Stock / inventory ─────────────────────────────────────────────────
        if ($this->matches($lq, ['залишок на складі', 'що на складі', 'обладнання на складі', 'скільки панел', 'скільки батаре', 'скільки інверт'])) {
            $equipment = $context['equipment_balance'] ?? [];
            if (!empty($equipment)) {
                $labels = ['panels' => 'Панелі', 'batteries' => 'Батареї', 'inverters' => 'Інвертори'];
                $lines  = [];
                foreach ($equipment as $key => $item) {
                    $name   = $labels[$key] ?? ucfirst($key);
                    $needed = $item['needed'] ?? 0;
                    $stock  = $item['stock'] ?? 0;
                    $diff   = $item['diff'] ?? ($stock - $needed);
                    $sign   = $diff >= 0 ? '+' : '';
                    $status = $diff >= 0 ? '✅' : '❌';
                    $lines[] = "{$status} {$name}: склад {$stock}, потреба {$needed} ({$sign}{$diff} шт)";
                }
                return "📦 **Баланс обладнання (склад vs потреба проектів):**\n" . implode("\n", $lines);
            }
        }

        // ── Stock list (all items) ─────────────────────────────────────────────
        if ($this->matches($lq, ['список складу', 'що є на складі', 'весь склад', 'перелік складу'])) {
            $rows = DB::table('solarglass_stock')->where('qty', '>', 0)->orderByDesc('qty')->get(['item_name', 'item_code', 'qty']);
            if ($rows->isEmpty()) return "📦 Склад порожній.";
            $lines = [];
            foreach ($rows->take(20) as $r) {
                $lines[] = "• {$r->item_name} ({$r->item_code}): {$r->qty} шт";
            }
            $total = $rows->count();
            $shown = min(20, $total);
            return "📦 **Склад SunFix (показано {$shown} з {$total} позицій):**\n" . implode("\n", $lines);
        }

        // ── This month expenses ───────────────────────────────────────────────
        if ($this->matches($lq, ['витрати цього місяця', 'витрати за місяць', 'витрати за цей місяць', 'що витратили цього місяця', 'які витрати цього місяця', 'витрати місяця'])) {
            return $this->monthlyExpenses(now()->format('Y-m'));
        }

        // ── Expenses by wallet (category substitute) ──────────────────────────
        if ($this->matches($lq, ['витрати по категоріях', 'категорії витрат', 'витрати по статтях', 'витрати по рахунках', 'де більше витрат'])) {
            $rows = DB::table('entries as e')
                ->join('wallets as w', 'e.wallet_id', '=', 'w.id')
                ->where('e.entry_type', 'expense')
                ->selectRaw('w.name as wallet, w.currency, COUNT(*) as cnt, SUM(e.amount) as total')
                ->groupBy('w.id', 'w.name', 'w.currency')
                ->orderByDesc('total')
                ->limit(15)
                ->get();

            if ($rows->isEmpty()) return null;
            $lines = [];
            foreach ($rows as $r) {
                $total   = number_format((float)$r->total, 0, '.', ' ');
                $lines[] = "• {$r->wallet} ({$r->currency}) — {$total} ({$r->cnt} записів)";
            }
            return "📊 **Витрати по рахунках:**\n" . implode("\n", $lines);
        }

        // ── Top expenses ──────────────────────────────────────────────────────
        if ($this->matches($lq, ['топ витрат', 'найбільші витрати', 'куди йдуть гроші'])) {
            $expenses = $context['top_expenses'] ?? [];
            if (empty($expenses)) return null;
            $lines = [];
            foreach (array_slice($expenses, 0, 10) as $i => $e) {
                $amount = number_format((float)($e['amount'] ?? 0), 0, '.', ' ');
                $lines[] = ($i + 1) . ". " . ($e['description'] ?? '?') . " — {$amount} грн";
            }
            return "💸 **Топ-10 витрат (UAH):**\n" . implode("\n", $lines);
        }

        // ── Active projects summary ───────────────────────────────────────────
        if ($this->matches($lq, ['активні проекти', 'скільки проектів', 'проекти в роботі', 'проектний pipeline', 'обсяг проектів'])) {
            $ap = $context['active_projects'] ?? [];
            if (empty($ap)) return null;
            $total    = $ap['total'] ?? 0;
            $pipeline = number_format((float)($ap['pipeline_uah'] ?? 0), 0, '.', ' ');
            $lines    = [];
            foreach ($ap['by_stage'] ?? [] as $s) {
                $lines[] = "• {$s['stage']}: {$s['count']} проектів";
            }
            return "🏗 **Активні проекти ({$total} всього, pipeline: {$pipeline} грн):**\n" . implode("\n", $lines);
        }

        // ── Most expensive projects ───────────────────────────────────────────
        if ($this->matches($lq, ['найдорожчий проект', 'дорогий проект', 'проект найдорожч', 'який проект найдорожч', 'найбільший проект'])) {
            $rows = DB::table('sales_projects')
                ->whereNull('closed_at')
                ->orderByDesc('total_amount')
                ->limit(5)
                ->get(['client_name', 'total_amount', 'currency', 'status']);

            if ($rows->isEmpty()) return "🏗 Активних проектів не знайдено.";
            $lines = [];
            foreach ($rows as $r) {
                $amount  = number_format((float)$r->total_amount, 0, '.', ' ');
                $lines[] = "• {$r->client_name} — {$amount} {$r->currency}";
            }
            return "🏗 **Найдорожчі активні проекти:**\n" . implode("\n", $lines);
        }

        // ── Monthly summary ───────────────────────────────────────────────────
        if ($this->matches($lq, ['місячний звіт', 'за місяць', 'прибуток за місяць', 'доходи і витрати', 'звіт по місяцях'])) {
            $summary = $context['monthly_summary'] ?? [];
            if (empty($summary)) return null;
            $lines = [];
            foreach ($summary as $month => $row) {
                $income  = number_format((float)($row['income'] ?? 0), 0, '.', ' ');
                $expense = number_format((float)($row['expense'] ?? 0), 0, '.', ' ');
                $net     = ($row['income'] ?? 0) - ($row['expense'] ?? 0);
                $netFmt  = number_format((float)$net, 0, '.', ' ');
                $sign    = $net >= 0 ? '+' : '';
                $lines[] = "• {$month}: доходи {$income}, витрати {$expense}, чистий {$sign}{$netFmt}";
            }
            return "📊 **Місячний огляд (UAH):**\n" . implode("\n", $lines);
        }

        // ── Salary by worker name ─────────────────────────────────────────────
        if ($this->matches($lq, ['зарплата', 'зарплатня', 'нарахування', 'заробіток', 'ставка'])) {
            $name = $this->extractPersonName($orig);

            if ($name) {
                $nameLower = mb_strtolower($name);
                $lines     = [];

                // 1. salary_rules — ставка (тариф)
                $rules = DB::table('salary_rules')
                    ->get()
                    ->filter(fn($r) => str_contains(mb_strtolower($r->staff_name), $nameLower));

                foreach ($rules as $r) {
                    $group = $r->staff_group ?? '?';
                    if ($r->mode === 'fixed' && $r->fixed_amount) {
                        $amount  = number_format((float)$r->fixed_amount, 0, '.', ' ');
                        $lines[] = "📋 Ставка: {$amount} {$r->currency}/міс ({$group}, фіксована)";
                    } elseif ($r->mode === 'piecework') {
                        if ($r->piecework_unit_rate) {
                            $lines[] = "📋 Ставка: {$r->piecework_unit_rate} {$r->currency}/панель ({$group}, відрядна)";
                        } elseif ($r->piecework_grid_le_50) {
                            $lines[] = "📋 Ставка: до 50кВт — {$r->piecework_grid_le_50}, більше 50кВт — {$r->piecework_grid_gt_50} {$r->currency} ({$group})";
                        }
                    }
                }

                // 2. salary_accruals — фактичні нарахування
                $accruals = DB::table('salary_accruals')
                    ->selectRaw('staff_name, currency, status, SUM(amount) as total, COUNT(*) as cnt')
                    ->groupBy('staff_name', 'currency', 'status')
                    ->get()
                    ->filter(fn($r) => str_contains(mb_strtolower($r->staff_name), $nameLower));

                foreach ($accruals as $r) {
                    $total       = number_format((float)$r->total, 2, '.', ' ');
                    $statusLabel = $r->status === 'paid' ? '✅ виплачено' : '⏳ очікує виплати';
                    $lines[]     = "💸 Нараховано: {$total} {$r->currency} ({$statusLabel}, {$r->cnt} записів)";
                }

                if (empty($lines)) {
                    return "💰 По співробітнику «{$name}» даних не знайдено. Перевір правопис.";
                }
                return "💰 **Зарплата — {$name}:**\n" . implode("\n", $lines);
            }

            // No name — show all salary rules
            $rules = DB::table('salary_rules')->orderBy('staff_name')->get();
            if ($rules->isEmpty()) {
                return "💰 Налаштувань зарплати не знайдено.";
            }
            $lines = [];
            foreach ($rules as $r) {
                if ($r->mode === 'fixed' && $r->fixed_amount) {
                    $amount  = number_format((float)$r->fixed_amount, 0, '.', ' ');
                    $lines[] = "• {$r->staff_name}: {$amount} {$r->currency}/міс (фіксована)";
                } elseif ($r->mode === 'piecework') {
                    $rate    = $r->piecework_unit_rate ?? $r->piecework_grid_le_50 ?? '?';
                    $lines[] = "• {$r->staff_name}: {$rate} {$r->currency} (відрядна)";
                }
            }
            return "💰 **Ставки всіх співробітників:**\n" . implode("\n", $lines);
        }

        // ── Deficiency projects ───────────────────────────────────────────────
        if ($this->matches($lq, ['недолік', 'є недоліки', 'проект з недоліками', 'дефект', 'не виправлені', 'виправили недоліки'])) {
            $rows = DB::table('sales_projects')
                ->whereIn('construction_status', ['has_deficiencies', 'deficiencies_fixed'])
                ->get(['client_name', 'construction_status', 'defects_note']);

            $legacy = DB::table('sales_projects')
                ->whereNotNull('defects_note')
                ->where('defects_note', '!=', '')
                ->whereNotIn('construction_status', ['has_deficiencies', 'deficiencies_fixed'])
                ->get(['client_name', 'construction_status', 'defects_note']);

            $all = $rows->merge($legacy);
            if ($all->isEmpty()) {
                return "✅ **Наразі немає проектів з активними недоліками.**";
            }
            $lines = [];
            foreach ($all as $p) {
                $status = match ($p->construction_status) {
                    'has_deficiencies'   => '❌ Не виправлено',
                    'deficiencies_fixed' => '🟡 Виправлено',
                    default              => '⚠️ Запис',
                };
                $note    = $p->defects_note ? ' — ' . mb_substr($p->defects_note, 0, 80) : '';
                $lines[] = "• {$p->client_name}: {$status}{$note}";
            }
            return "🔍 **Проекти з недоліками ({$all->count()}):**\n" . implode("\n", $lines);
        }

        // ── Reclamations ──────────────────────────────────────────────────────
        if ($this->matches($lq, ['рекламац', 'скарг', 'відкрит', 'відкриті рекламац'])) {
            $rows = DB::table('reclamations')
                ->where('status', 'open')
                ->orderByDesc('created_at')
                ->limit(20)
                ->get(['code', 'last_name', 'city', 'problem', 'created_at']);

            $total = DB::table('reclamations')->where('status', 'open')->count();
            if ($rows->isEmpty()) return "✅ Відкритих рекламацій немає.";
            $lines = [];
            foreach ($rows as $r) {
                $problem = $r->problem ? mb_substr($r->problem, 0, 60) : '—';
                $lines[] = "• [{$r->code}] {$r->last_name}, {$r->city} — {$problem}";
            }
            return "🛠 **Відкриті рекламації ({$total}):**\n" . implode("\n", $lines);
        }

        // ── Service requests ──────────────────────────────────────────────────
        if ($this->matches($lq, ['сервісні заявки', 'заявки на сервіс', 'відкриті заявки', 'service request'])) {
            $rows = DB::table('service_requests')
                ->where('status', 'open')
                ->orderByDesc('created_at')
                ->limit(20)
                ->get(['client_name', 'settlement', 'electrician', 'scheduled_date', 'status']);

            $total = DB::table('service_requests')->where('status', 'open')->count();
            if ($rows->isEmpty()) return "✅ Відкритих сервісних заявок немає.";
            $lines = [];
            foreach ($rows as $r) {
                $date    = $r->scheduled_date ?? '—';
                $lines[] = "• {$r->client_name}, {$r->settlement} | {$r->electrician} | {$date}";
            }
            return "⚙️ **Відкриті сервісні заявки ({$total}):**\n" . implode("\n", $lines);
        }

        return null;
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    private function monthlyExpenses(string $month): string
    {
        $rows = DB::table('entries')
            ->where('entry_type', 'expense')
            ->whereRaw("strftime('%Y-%m', posting_date) = ?", [$month])
            ->orderByDesc('amount')
            ->limit(15)
            ->get(['title', 'amount', 'posting_date']);

        $total = DB::table('entries')
            ->where('entry_type', 'expense')
            ->whereRaw("strftime('%Y-%m', posting_date) = ?", [$month])
            ->sum('amount');

        if ($rows->isEmpty()) {
            return "📤 **Витрати за {$month}:** поки немає записів.";
        }
        $lines = [];
        foreach ($rows as $r) {
            $title   = $r->title ? mb_substr($r->title, 0, 60) : '—';
            $amount  = number_format((float)$r->amount, 0, '.', ' ');
            $lines[] = "• {$title} — {$amount} грн ({$r->posting_date})";
        }
        $totalFmt = number_format((float)$total, 0, '.', ' ');
        return "📤 **Витрати за {$month} (топ-15, всього {$totalFmt} грн):**\n" . implode("\n", $lines);
    }

    /**
     * Compute wallet balances via DB (fallback if context unavailable)
     */
    private function computeWalletBalances(): array
    {
        try {
            $rows = DB::table('wallets as w')
                ->where('w.is_active', 1)
                ->leftJoin('entries as e', 'e.wallet_id', '=', 'w.id')
                ->selectRaw("w.currency, COALESCE(SUM(CASE WHEN e.entry_type='income' THEN e.amount ELSE -e.amount END),0) as balance")
                ->groupBy('w.id', 'w.currency')
                ->get();

            $out = [];
            foreach ($rows as $r) {
                $out[$r->currency] = ($out[$r->currency] ?? 0) + $r->balance;
            }
            return $out;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Extract person name from question (uses original case).
     * "яка зарплата у Малінін" → "Малінін"
     * "зарплата Кукуяки"      → "Кукуяки"
     */
    private function extractPersonName(string $q): ?string
    {
        // After prepositions: "у Малінін", "в Малінін", "для Малінін"
        if (preg_match('/(?:у|в|для|по)\s+([а-яіїєґА-ЯІЇЄҐ][а-яіїєґА-ЯІЇЄҐ\'-]{2,})/u', $q, $m)) {
            return $m[1];
        }
        // Keyword followed by name (only if first letter is uppercase = proper name)
        if (preg_match('/(?:зарплата|зарплатня|нарахування|заробіток)\s+([А-ЯІЇЄҐ][а-яіїєґА-ЯІЇЄҐ\'-]{2,})/u', $q, $m)) {
            return $m[1];
        }
        return null;
    }

    /**
     * Returns true if the question looks like it needs DB data.
     * Prevents wasting Ollama 5s timeout on general chat questions.
     */
    private function looksLikeDataQuestion(string $lq): bool
    {
        $dataKeywords = [
            'скільки', 'який', 'яка', 'яке', 'які', 'хто', 'де', 'коли',
            'список', 'покажи', 'виведи', 'перелік', 'знайди', 'порівняй',
            'проект', 'клієнт', 'склад', 'гаманець', 'баланс', 'витрат',
            'дохід', 'зарплат', 'рекламац', 'сервіс', 'заявк', 'накладн',
            'рахунок', 'рахунки', 'монтаж', 'електрик', 'панел', 'батарей',
            'інвертор', 'поставк', 'борг', 'оплат', 'залишок', 'нарахуван',
        ];
        foreach ($dataKeywords as $kw) {
            if (str_contains($lq, $kw)) return true;
        }
        return false;
    }

    private function matches(string $text, array $keywords): bool
    {
        foreach ($keywords as $kw) {
            if (str_contains($text, $kw)) return true;
        }
        return false;
    }

    private function aiLog(string $model, string $question, string $response, int $ms, ?int $userId, int $status = 200): void
    {
        try {
            Log::channel('ai')->info('AI chat', [
                'model'  => $model,
                'uid'    => $userId,
                'ms'     => $ms,
                'status' => $status,
                'q'      => mb_substr($question, 0, 150),
                'a'      => mb_substr($response, 0, 200),
            ]);
        } catch (\Throwable) {
            // Never fail because of logging
        }
    }

    private function persistLog(?int $userId, string $question, string $response, int $statusCode, int $ms): void
    {
        try {
            DB::table('ai_logs')->insert([
                'user_id'     => $userId,
                'question'    => $question,
                'response'    => $response,
                'status_code' => $statusCode,
                'duration_ms' => $ms,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        } catch (\Throwable) {
            // Never fail because of logging
        }
    }
}
