<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Services\AI\AIChatRouter;
use App\Services\AI\AIAgentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AIChatController extends Controller
{
    public function __construct(
        private AIChatRouter  $router,
        private AIAgentService $agent,
    ) {}

    public function chat(Request $request)
    {
        $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'model'   => ['sometimes', 'string', 'in:local,claude'],
        ]);

        $question = trim($request->input('message'));
        $model    = $request->input('model', 'local');
        $user     = auth()->user();
        $started  = microtime(true);

        $context = $this->agent->collectContext();

        // Quick SQL-free responses for common questions
        $quick = $this->quickAnswer($question, $context);
        if ($quick !== null) {
            $durationMs = (int) round((microtime(true) - $started) * 1000);
            $this->aiLog('quick', $question, $quick, $durationMs, $user?->id);
            return response()->json([
                'response'    => $quick,
                'duration_ms' => $durationMs,
                'model_used'  => 'quick',
            ]);
        }

        $system = <<<'PROMPT'
Ти — фінансовий аналітик компанії SolarGlass (сонячна енергетика, Україна).
Аналізуй витрати, проекти, склад, грошові потоки. Давай прогнози і чіткі фінансові висновки.
Відповідай виключно українською мовою. Будь конкретним і лаконічним.
Якщо контекст містить числові дані — використовуй їх у відповіді.
PROMPT;

        $result = $this->router->handle($question, $model, $context, $system);

        $durationMs = $result['duration_ms'];
        $this->aiLog($result['model_used'], $question, $result['response'], $durationMs, $user?->id, $result['status_code']);

        // Persist to DB (best-effort)
        try {
            DB::table('ai_logs')->insert([
                'user_id'     => $user?->id,
                'question'    => $question,
                'response'    => $result['response'],
                'status_code' => $result['status_code'],
                'duration_ms' => $durationMs,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('AIChatController: failed to log AI interaction', ['error' => $e->getMessage()]);
        }

        return response()->json([
            'response'    => $result['response'],
            'duration_ms' => $durationMs,
            'model_used'  => $result['model_used'],
        ]);
    }

    // ---------------------------------------------------------------------------

    private function aiLog(string $model, string $question, string $response, int $ms, ?int $userId, int $status = 200): void
    {
        try {
            Log::channel('ai')->info('AI chat', [
                'model'       => $model,
                'user_id'     => $userId,
                'duration_ms' => $ms,
                'status'      => $status,
                'q'           => mb_substr($question, 0, 200),
                'a'           => mb_substr($response, 0, 300),
            ]);
        } catch (\Throwable) {}
    }

    /**
     * Return a formatted Ukrainian answer directly from context for common keywords.
     * Returns null if the question doesn't match any quick pattern.
     */
    private function quickAnswer(string $q, array $context): ?string
    {
        $lq = mb_strtolower($q);

        // Wallet balances
        if ($this->matches($lq, ['скільки грошей', 'баланс гаманц', 'залишок гаманц', 'скільки коштів', 'гроші на рахунк', 'скільки на рахунк'])) {
            $balances = $context['wallet_balances'] ?? [];
            if (empty($balances)) return null;
            $lines = [];
            foreach ($balances as $currency => $amount) {
                $lines[] = "• {$currency}: " . number_format((float)$amount, 2, '.', ' ');
            }
            return "💼 **Поточні залишки по гаманцях:**\n" . implode("\n", $lines);
        }

        // Stock / inventory
        if ($this->matches($lq, ['залишок на складі', 'що на складі', 'склад', 'обладнання на складі', 'скільки панел', 'скільки батаре', 'скільки інверт'])) {
            $equipment = $context['equipment_balance'] ?? [];
            if (empty($equipment)) return null;
            $labels = [
                'panels'    => 'Панелі',
                'batteries' => 'Батареї',
                'inverters' => 'Інвертори',
            ];
            $lines = [];
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

        // Top expenses
        if ($this->matches($lq, ['витрати', 'на що витратили', 'топ витрат', 'найбільші витрати', 'куди йдуть гроші'])) {
            $expenses = $context['top_expenses'] ?? [];
            if (empty($expenses)) return null;
            $lines = [];
            foreach (array_slice($expenses, 0, 10) as $i => $e) {
                $name   = $e['description'] ?? $e['name'] ?? '?';
                $amount = number_format((float)($e['amount'] ?? 0), 0, '.', ' ');
                $lines[] = ($i + 1) . ". {$name} — {$amount} грн";
            }
            return "💸 **Топ-10 витрат (UAH):**\n" . implode("\n", $lines);
        }

        // Active projects summary
        if ($this->matches($lq, ['активні проекти', 'скільки проектів', 'проекти в роботі', 'проектний pipeline', 'обсяг проектів'])) {
            $ap = $context['active_projects'] ?? [];
            if (empty($ap)) return null;
            $total    = $ap['total'] ?? 0;
            $pipeline = number_format((float)($ap['pipeline_uah'] ?? 0), 0, '.', ' ');
            $lines    = [];
            foreach ($ap['by_stage'] ?? [] as $stage) {
                $cnt  = $stage['count'] ?? 0;
                $name = $stage['stage'] ?? '?';
                $lines[] = "• {$name}: {$cnt} проектів";
            }
            return "🏗 **Активні проекти ({$total} всього, pipeline: {$pipeline} грн):**\n" . implode("\n", $lines);
        }

        // Monthly summary
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

        // Deficiency projects
        if ($this->matches($lq, ['недолік', 'є недоліки', 'проект з недоліками', 'дефект', 'не виправлені', 'виправили недоліки'])) {
            $rows = \Illuminate\Support\Facades\DB::table('sales_projects as sp')
                ->whereIn('sp.construction_status', ['has_deficiencies', 'deficiencies_fixed'])
                ->select('sp.client_name', 'sp.construction_status', 'sp.defects_note')
                ->get();

            // Also check legacy defects_note
            $legacyRows = \Illuminate\Support\Facades\DB::table('sales_projects')
                ->whereNotNull('defects_note')
                ->where('defects_note', '!=', '')
                ->whereNotIn('construction_status', ['has_deficiencies', 'deficiencies_fixed'])
                ->select('client_name', 'construction_status', 'defects_note')
                ->get();

            $all = $rows->merge($legacyRows);
            if ($all->isEmpty()) {
                return "✅ **Наразі немає проектів з активними недоліками.**\nУсі проекти чисті.";
            }

            $lines = [];
            foreach ($all as $p) {
                $status = match ($p->construction_status) {
                    'has_deficiencies'  => '❌ Не виправлено',
                    'deficiencies_fixed'=> '🟡 Виправлено (очікує підтвердження)',
                    default             => '⚠️ Застарілий запис',
                };
                $note = $p->defects_note ? ' — ' . mb_substr($p->defects_note, 0, 80) : '';
                $lines[] = "• {$p->client_name}: {$status}{$note}";
            }
            return "🔍 **Проекти з недоліками (" . $all->count() . "):**\n" . implode("\n", $lines);
        }

        return null;
    }

    private function matches(string $text, array $keywords): bool
    {
        foreach ($keywords as $kw) {
            if (str_contains($text, $kw)) return true;
        }
        return false;
    }
}
