<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIAgentService
{
    private string $endpoint;
    private int $timeout;

    public function __construct()
    {
        $this->endpoint = rtrim((string) config('services.openclaw.url', 'http://localhost:9000'), '/') . '/ai/finance';
        $this->timeout  = (int) config('services.openclaw.timeout', 30);
    }

    /**
     * Ask a question with full financial context.
     *
     * @return array{response: string, duration_ms: int, status_code: int}
     */
    public function ask(string $question): array
    {
        $started = microtime(true);

        $context = $this->collectContext();

        $payload = [
            'question' => $question,
            'context'  => $context,
            'system'   => 'You are a financial analyst for SolarGlass company. '
                        . 'You analyze company finances, expenses, projects and stock. '
                        . 'Provide forecasts and financial insights. '
                        . 'Answer in Ukrainian. Be concise and actionable.',
        ];

        try {
            $response = Http::timeout($this->timeout)
                ->acceptJson()
                ->post($this->endpoint, $payload);

            $durationMs  = (int) round((microtime(true) - $started) * 1000);
            $statusCode  = $response->status();

            if (!$response->successful()) {
                Log::warning('AIAgentService: OpenClaw returned non-2xx', [
                    'status' => $statusCode,
                    'body'   => mb_substr($response->body(), 0, 500),
                ]);
                return [
                    'response'    => 'Сервіс ШІ тимчасово недоступний (HTTP ' . $statusCode . ').',
                    'duration_ms' => $durationMs,
                    'status_code' => $statusCode,
                ];
            }

            $body = $response->json();
            $text = $body['response'] ?? $body['answer'] ?? $body['text'] ?? $body['message'] ?? null;

            if (!$text) {
                $text = $response->body();
            }

            return [
                'response'    => (string) $text,
                'duration_ms' => $durationMs,
                'status_code' => $statusCode,
            ];
        } catch (\Throwable $e) {
            $durationMs = (int) round((microtime(true) - $started) * 1000);
            Log::error('AIAgentService: request failed', ['error' => $e->getMessage()]);

            return [
                'response'    => 'Не вдалося підключитися до AI-сервісу: ' . $e->getMessage(),
                'duration_ms' => $durationMs,
                'status_code' => 0,
            ];
        }
    }

    // ─────────────────────────────────────────────────────────────
    // Context collection
    // ─────────────────────────────────────────────────────────────

    public function collectContext(): array
    {
        return [
            'wallet_balances'   => $this->walletBalances(),
            'monthly_summary'   => $this->monthlySummary(),
            'active_projects'   => $this->activeProjects(),
            'equipment_balance' => $this->equipmentBalance(),
            'top_expenses'      => $this->topExpensesThisMonth(),
        ];
    }

    private function walletBalances(): array
    {
        $rows = DB::table('entries as e')
            ->join('wallets as w', 'w.id', '=', 'e.wallet_id')
            ->where('e.entry_type', '!=', 'reversal')
            ->select(
                'w.currency',
                DB::raw("SUM(CASE WHEN e.entry_type='income' THEN e.amount ELSE -e.amount END) as balance")
            )
            ->groupBy('w.currency')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $result[$row->currency] = round((float) $row->balance, 2);
        }
        return $result;
    }

    private function monthlySummary(): array
    {
        $rows = DB::table('entries')
            ->where('entry_type', '!=', 'reversal')
            ->select(
                DB::raw("strftime('%Y-%m', posting_date) as month"),
                'entry_type',
                DB::raw('SUM(amount) as total')
            )
            ->groupBy('month', 'entry_type')
            ->orderBy('month')
            ->get()
            ->groupBy('month');

        $result = [];
        foreach ($rows as $month => $items) {
            $result[$month] = [
                'income'  => round((float) $items->where('entry_type', 'income')->sum('total'), 2),
                'expense' => round((float) $items->where('entry_type', 'expense')->sum('total'), 2),
            ];
        }

        // return last 6 months only
        return array_slice($result, -6, 6, true);
    }

    private function activeProjects(): array
    {
        $stageLabels = [
            38556547 => 'Частково оплатив',
            69586234 => 'Комплектація',
            38556550 => 'Очікування доставки',
            69593822 => 'Заплановане будівництво',
            69593826 => 'Монтаж',
            69593830 => 'Електрична частина',
            69593834 => 'Здача проекту',
        ];

        $counts = DB::table('amocrm_deal_map')
            ->whereNotNull('amo_status_id')
            ->groupBy('amo_status_id')
            ->select('amo_status_id', DB::raw('count(*) as cnt'))
            ->get()
            ->keyBy('amo_status_id');

        $result = [];
        foreach ($stageLabels as $id => $label) {
            $cnt = $counts[$id]->cnt ?? 0;
            if ($cnt > 0) {
                $result[] = ['stage' => $label, 'count' => (int) $cnt];
            }
        }

        // Total pipeline value
        $totalAmount = DB::table('amocrm_deal_map as m')
            ->join('sales_projects as p', 'p.id', '=', 'm.wallet_project_id')
            ->whereIn('m.amo_status_id', array_keys($stageLabels))
            ->whereNotNull('p.total_amount')
            ->sum('p.total_amount');

        return [
            'by_stage'    => $result,
            'total'       => array_sum(array_column($result, 'count')),
            'pipeline_uah'=> round((float) $totalAmount, 2),
        ];
    }

    private function equipmentBalance(): array
    {
        $stageIds = [38556547, 69586234, 38556550, 69593822, 69593826, 69593830, 69593834];

        $needed = DB::table('amocrm_deal_map as m')
            ->join('sales_projects as p', 'p.id', '=', 'm.wallet_project_id')
            ->whereIn('m.amo_status_id', $stageIds)
            ->select(
                DB::raw('SUM(COALESCE(p.panel_qty, 0)) as panels'),
                DB::raw('SUM(COALESCE(p.battery_qty, 0)) as batteries'),
                DB::raw("COUNT(CASE WHEN p.inverter IS NOT NULL AND p.inverter != '' AND p.inverter != '-' THEN 1 END) as inverters")
            )->first();

        $stock = [
            'panels'    => (int) DB::table('solarglass_stock')->where('item_name', 'like', 'Фотомодул%')->sum('qty'),
            'batteries' => (int) DB::table('solarglass_stock')->where('item_name', 'like', 'АКБ%')->sum('qty'),
            'inverters' => (int) DB::table('solarglass_stock')
                ->where(function ($q) {
                    $q->where('item_name', 'like', 'Інвертор%')
                      ->orWhere('item_name', 'like', 'інвертор%');
                })->sum('qty'),
        ];

        return [
            'panels'    => ['needed' => (int)$needed->panels,    'stock' => $stock['panels'],    'diff' => $stock['panels']    - (int)$needed->panels],
            'batteries' => ['needed' => (int)$needed->batteries, 'stock' => $stock['batteries'], 'diff' => $stock['batteries'] - (int)$needed->batteries],
            'inverters' => ['needed' => (int)$needed->inverters, 'stock' => $stock['inverters'], 'diff' => $stock['inverters'] - (int)$needed->inverters],
        ];
    }

    private function topExpensesThisMonth(): array
    {
        $now = now();

        $rows = DB::table('entries')
            ->where('entry_type', 'expense')
            ->whereYear('posting_date', $now->year)
            ->whereMonth('posting_date', $now->month)
            ->select(DB::raw("COALESCE(NULLIF(title,''), NULLIF(comment,''), '—') as label"), DB::raw('SUM(amount) as total'))
            ->whereRaw("(title IS NOT NULL AND title != '') OR (comment IS NOT NULL AND comment != '')")
            ->groupBy('label')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        return $rows->map(fn ($r) => [
            'description' => $r->label,
            'amount'      => round((float) $r->total, 2),
        ])->toArray();
    }
}
