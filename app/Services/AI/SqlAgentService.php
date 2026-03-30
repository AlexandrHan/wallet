<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * AI SQL Agent — converts natural-language questions into SELECT queries,
 * executes them, and formats the result in Ukrainian.
 *
 * NEVER throws — all errors are swallowed and null is returned.
 * Flow: generateSql() → isValid() → execute() → format()
 */
class SqlAgentService
{
    // -------------------------------------------------------------------------
    // Compact schema for Ollama (shorter = faster generation)
    // -------------------------------------------------------------------------
    private const SCHEMA = <<<'S'
SQLite tables (SolarGlass ERP):
sales_projects(id,client_name,phone_number,total_amount,advance_amount,remaining_amount,currency,status,construction_status,defects_note,inverter,panel_name,panel_qty,battery_name,battery_qty,electrician,installation_team,created_at,closed_at,cancelled_at)
  -- construction_status: has_deficiencies|deficiencies_fixed|salary_paid|NULL
  -- For Ukrainian client names always use LIKE: WHERE LOWER(client_name) LIKE '%keyword%'
  -- Names may be in genitive form (e.g. "Петрова" -> search '%петров%', "Іваненка" -> '%іваненк%')
  -- Skip cancelled projects: WHERE cancelled_at IS NULL
entries(id,wallet_id,posting_date,entry_type,amount,title,comment,created_at)
  -- entry_type: income|expense|reversal
wallets(id,name,currency,type,is_active)
  -- no balance column; compute: SUM(CASE WHEN entry_type='income' THEN amount ELSE -amount END)
solarglass_stock(id,item_code,item_name,qty)
salary_accruals(id,project_id,user_id,staff_name,staff_group,amount,currency,status,paid_at,created_at)
  -- status: pending|paid
quality_checks(id,project_id,status,deficiencies,created_at)
  -- status: pending|has_deficiencies|deficiencies_fixed|approved
reclamations(id,code,last_name,city,status,problem,created_at)
service_requests(id,client_name,settlement,status,scheduled_date,created_at)
S;

    private const BLOCKED = ['DELETE','UPDATE','INSERT','DROP','ALTER','TRUNCATE','REPLACE','CREATE','ATTACH'];

    public function __construct(
        private ClaudeService $claude,
    ) {}

    // =========================================================================
    // Public entry point — NEVER throws
    // =========================================================================

    /**
     * @return array{response:string,sql:string,row_count:int,duration_ms:int,model_used:string}|null
     */
    public function handle(string $question, string $model = 'local'): ?array
    {
        $started = microtime(true);

        try {
            $sql = $this->generateSql($question, $model);
        } catch (\Throwable $e) {
            $this->log('warning', 'generateSql threw', ['error' => $e->getMessage()]);
            $sql = null;
        }

        if (!$sql) {
            return null;
        }

        if (!$this->isValid($sql)) {
            $this->log('warning', 'SQL blocked', ['sql' => $sql]);
            return null;
        }

        $rows = $this->execute($sql);
        if ($rows === null) {
            return null;
        }

        $response   = $this->format($question, $sql, $rows);
        $durationMs = (int) round((microtime(true) - $started) * 1000);

        $this->log('info', 'success', [
            'q'         => mb_substr($question, 0, 100),
            'sql'       => $sql,
            'rows'      => count($rows),
            'ms'        => $durationMs,
        ]);

        return [
            'response'    => $response,
            'sql'         => $sql,
            'row_count'   => count($rows),
            'duration_ms' => $durationMs,
            'model_used'  => 'sql-agent',
        ];
    }

    // =========================================================================
    // Step 1 — Generate SQL (5 second timeout, never blocks)
    // =========================================================================

    public function generateSql(string $question, string $model = 'local'): ?string
    {
        // Try Claude if available (fast, reliable)
        if ($model === 'claude' && $this->claude->isAvailable()) {
            try {
                $result = $this->claude->ask(
                    self::SCHEMA . "\n\nQuestion: {$question}\n\nSQL:",
                    [],
                    'Return ONLY a SQLite SELECT. No explanation, no markdown. If unsure return NULL.'
                );
                $raw = trim($result['response'] ?? '');
                if ($raw && strtoupper($raw) !== 'NULL') {
                    return $this->extractSql($raw);
                }
            } catch (\Throwable) {
                // fall through to Ollama
            }
        }

        // Direct Ollama call — 5 second timeout
        return $this->ollamaGenerateSql($question);
    }

    private function ollamaGenerateSql(string $question): ?string
    {
        $url   = rtrim((string) config('services.ollama.url', 'http://localhost:11434'), '/');
        $model = (string) config('services.ollama.model', 'mistral');

        try {
            $response = Http::timeout(5)->post("{$url}/api/chat", [
                'model'  => $model,
                'stream' => false,
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a SQLite expert for a Ukrainian ERP. Return ONLY a SELECT statement. No explanation, no markdown, no backticks. Return NULL if unsure. For Ukrainian names use LIKE: WHERE LOWER(client_name) LIKE \'%keyword%\'. Names may be in genitive case — strip last 1-2 chars for the search stem. Always exclude cancelled: WHERE cancelled_at IS NULL.'],
                    ['role' => 'user',   'content' => self::SCHEMA . "\n\nQuestion: {$question}\nSQL:"],
                ],
            ]);

            if (!$response->successful()) {
                return null;
            }

            $raw = trim($response->json('message.content', ''));
            if (!$raw || strtoupper($raw) === 'NULL') {
                return null;
            }

            return $this->extractSql($raw);
        } catch (\Throwable $e) {
            // Timeout or connection error — silently skip
            $this->log('info', 'Ollama timeout/error (skipped)', ['error' => mb_substr($e->getMessage(), 0, 80)]);
            return null;
        }
    }

    // =========================================================================
    // Step 2 — Validate (SELECT only, no dangerous keywords)
    // =========================================================================

    public function isValid(string $sql): bool
    {
        $upper = strtoupper(trim($sql));
        if (!str_starts_with($upper, 'SELECT')) {
            return false;
        }
        foreach (self::BLOCKED as $kw) {
            if (str_contains($upper, $kw)) {
                return false;
            }
        }
        return true;
    }

    // =========================================================================
    // Step 3 — Execute (max 50 rows, never throws)
    // =========================================================================

    public function execute(string $sql): ?array
    {
        $sql = $this->injectLimit($sql, 50);
        try {
            return DB::select($sql);
        } catch (\Throwable $e) {
            $this->log('warning', 'Query failed', ['sql' => $sql, 'error' => $e->getMessage()]);
            return null;
        }
    }

    // =========================================================================
    // Step 4 — Format result in Ukrainian (PHP only, no AI call)
    // =========================================================================

    public function format(string $question, string $sql, array $rows): string
    {
        if (empty($rows)) {
            return "🔍 Запит виконано — результатів не знайдено.";
        }

        $count = count($rows);
        $cols  = array_keys((array) $rows[0]);
        $lines = [];

        foreach ($rows as $row) {
            $row   = (array) $row;
            $parts = [];
            foreach ($cols as $col) {
                $val = $row[$col] ?? null;
                if ($val === null || $val === '') continue;
                $parts[] = $this->colLabel($col) . ': ' . $this->formatValue($col, $val);
            }
            if ($parts) {
                $lines[] = '• ' . implode(' | ', $parts);
            }
        }

        $noun   = match (true) { $count === 1 => 'запис', $count < 5 => 'записи', default => 'записів' };
        $header = "📊 **{$question}** — {$count} {$noun}:";
        return $header . "\n" . implode("\n", $lines);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function extractSql(string $raw): ?string
    {
        // Markdown fences
        if (preg_match('/```(?:sql)?\s*(SELECT[\s\S]+?)```/i', $raw, $m)) {
            return trim($m[1]);
        }
        // First SELECT in text
        if (preg_match('/(SELECT\s[\s\S]+)/i', $raw, $m)) {
            $sql = preg_split('/\n\n|;\s*\n/', $m[1])[0];
            return trim($sql, " \t\n\r;");
        }
        return null;
    }

    private function injectLimit(string $sql, int $limit): string
    {
        if (stripos($sql, 'LIMIT') === false) {
            return rtrim(rtrim($sql), ';') . " LIMIT {$limit}";
        }
        return $sql;
    }

    private function colLabel(string $col): string
    {
        return match ($col) {
            'id'                   => 'ID',
            'client_name'          => 'Клієнт',
            'last_name'            => 'Прізвище',
            'name'                 => 'Назва',
            'total_amount'         => 'Сума',
            'advance_amount'       => 'Аванс',
            'remaining_amount'     => 'Залишок',
            'balance'              => 'Баланс',
            'amount', 'total'      => 'Сума',
            'currency'             => 'Валюта',
            'status'               => 'Статус',
            'construction_status'  => 'Стан',
            'defects_note',
            'deficiencies'         => 'Недоліки',
            'electrician'          => 'Електрик',
            'installation_team'    => 'Монтаж',
            'panel_qty'            => 'Панелі',
            'battery_qty'          => 'АКБ',
            'qty'                  => 'К-сть',
            'item_code'            => 'Код',
            'item_name'            => 'Назва',
            'inverter'             => 'Інвертор',
            'panel_name'           => 'Панелі (модель)',
            'battery_name'         => 'АКБ (модель)',
            'phone_number'         => 'Телефон',
            'remaining_amount'     => 'Залишок оплати',
            'advance_amount'       => 'Аванс',
            'staff_name'           => 'Співробітник',
            'staff_group'          => 'Група',
            'entry_type'           => 'Тип',
            'title'                => 'Опис',
            'posting_date',
            'created_at',
            'paid_at',
            'closed_at'            => 'Дата',
            'scheduled_date'       => 'Запланована дата',
            'city'                 => 'Місто',
            'phone'                => 'Телефон',
            'settlement'           => 'Населений пункт',
            'problem'              => 'Проблема',
            'code'                 => 'Код',
            'cnt', 'count'         => 'Кількість',
            default                => $col,
        };
    }

    private function formatValue(string $col, mixed $val): string
    {
        if ($val === null || $val === '') return '—';

        $moneyColumns = ['total_amount','advance_amount','remaining_amount','amount','balance','total'];
        if (in_array($col, $moneyColumns, true) && is_numeric($val)) {
            return number_format((float) $val, 2, '.', ' ');
        }

        if ($col === 'construction_status') {
            return match ($val) {
                'has_deficiencies'   => '❌ Є недоліки',
                'deficiencies_fixed' => '🟡 Виправлено',
                'salary_paid'        => '✅ Зарплата виплачена',
                default              => (string) $val,
            };
        }

        if ($col === 'entry_type') {
            return match ($val) {
                'income'   => '📥 Прихід',
                'expense'  => '📤 Витрата',
                'reversal' => '↩️ Сторно',
                default    => (string) $val,
            };
        }

        if ($col === 'status') {
            return match ($val) {
                'has_deficiencies'   => '❌ Є недоліки',
                'deficiencies_fixed' => '🟡 Виправлено',
                'approved'           => '✅ Затверджено',
                'pending'            => '⏳ Очікує',
                'paid'               => '✅ Виплачено',
                'open'               => '🔴 Відкрито',
                'done'               => '✅ Виконано',
                'active'             => '🟢 Активний',
                default              => (string) $val,
            };
        }

        $str = (string) $val;
        return mb_strlen($str) > 100 ? mb_substr($str, 0, 97) . '…' : $str;
    }

    private function log(string $level, string $msg, array $ctx = []): void
    {
        try {
            Log::channel('ai')->{$level}("SqlAgent: {$msg}", $ctx);
        } catch (\Throwable) {
            // Never fail because of logging
        }
    }
}
