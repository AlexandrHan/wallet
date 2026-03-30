<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Services\AI\AIChatRouter;
use App\Services\AI\AIAgentService;
use App\Services\AI\IntentService;
use App\Services\AI\SqlAgentService;
use App\Services\AmoSearchService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
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
        private AIChatRouter        $router,
        private AIAgentService      $agent,
        private SqlAgentService     $sqlAgent,
        private IntentService       $intentService,
        private NotificationService $notifications,
        private AmoSearchService    $amoSearch,
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

        // ── 0. AmoCRM search ─────────────────────────────────────────────────
        if ($this->isAmoSearch($lq)) {
            try {
                $amoQuery = $this->extractAmoQuery($question);
                Log::info('AI amo-search: triggered', ['original' => $question, 'query' => $amoQuery]);
                $amoHits  = $this->amoSearch->search($amoQuery);
                $stock    = $this->getStockInfo($amoQuery);
                $needed   = $this->extractRequiredQty($amoHits);
                Log::info('AI amo-search: stock', ['query' => $amoQuery, 'found' => $stock['found'], 'qty' => $stock['qty'], 'needed' => $needed]);
                $answer   = $this->formatAmoResults($amoHits, $amoQuery)
                          . $this->formatStockSection($stock, $needed);
            } catch (\Throwable $e) {
                Log::warning('AI: amo search error', ['err' => $e->getMessage()]);
                $answer = '❌ Помилка під час пошуку в amoCRM. Спробуй ще раз.';
            }
            $ms = (int) round((microtime(true) - $started) * 1000);
            $this->aiLog('amo-search', $question, $answer, $ms, $user?->id);
            return $this->json($answer, 'amo-search', $ms);
        }

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

        // ── 1.5. Intent router ────────────────────────────────────────────────
        // AI-based intent detection (2s timeout) + keyword fallback.
        // Runs only when quick patterns returned nothing.
        try {
            $intent       = $this->intentService->detectIntent($question);
            $intentAnswer = $intent ? $this->dispatchByIntent($intent, $question, $context) : null;
        } catch (\Throwable) {
            $intent       = null;
            $intentAnswer = null;
        }

        if ($intentAnswer !== null) {
            $ms = (int) round((microtime(true) - $started) * 1000);
            $this->aiLog('intent:' . ($intent ?? 'unknown'), $question, $intentAnswer, $ms, $user?->id);
            $this->maybeNotify($user?->id, $intent ?? '', $intentAnswer);
            return $this->json($intentAnswer, 'intent', $ms);
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
    // AmoCRM search helpers
    // =========================================================================

    /**
     * Known product aliases: fuzzy user input → canonical AmoCRM search string.
     * Keys are lowercase, matched via str_contains on normalised input.
     */
    private const PRODUCT_ALIASES = [
        'boost 6k g4'  => 'X1 BOOST 6K G4',
        'boost 6к г4'  => 'X1 BOOST 6K G4',
        'boost 6k'     => 'X1 BOOST 6K G4',
        'boost 6к'     => 'X1 BOOST 6K G4',
        'boost 5k g4'  => 'X1 BOOST 5K G4',
        'boost 5к г4'  => 'X1 BOOST 5K G4',
        'boost 5k'     => 'X1 BOOST 5K G4',
        'boost 5к'     => 'X1 BOOST 5K G4',
        'boost 4k'     => 'X1 BOOST 4K G4',
        'boost 4к'     => 'X1 BOOST 4K G4',
        'boost 3k'     => 'X1 BOOST 3K G4',
        'boost 3к'     => 'X1 BOOST 3K G4',
        'boost 3.6k'   => 'X1 BOOST 3.6K G4',
        'boost 3 6k'   => 'X1 BOOST 3.6K G4',
        'mini 6k'      => 'X1 MINI 6K G4',
        'mini 5k'      => 'X1 MINI 5K G4',
        'mini 4k'      => 'X1 MINI 4K G4',
        'fit 5k'       => 'X1-FIT 5K',
        'fit 6k'       => 'X1-FIT 6K',
        'fit 7.5k'     => 'X1-FIT 7.5K',
        'hybrid 6k'    => 'X1 HYBRID 6K',
        'hybrid 5k'    => 'X1 HYBRID 5K',
        'hybrid 10k'   => 'X3 HYBRID 10K',
        'hybrid 12k'   => 'X3 HYBRID 12K',
        'hybrid 15k'   => 'X3 HYBRID 15K',
        'hybrid 20k'   => 'X3 HYBRID 20K',
        'hybrid 30k'   => 'X3 HYBRID 30K',
        'x3 10k'       => 'X3 HYBRID 10K',
        'x3 12k'       => 'X3 HYBRID 12K',
        'x3 15k'       => 'X3 HYBRID 15K',
        'x3 20k'       => 'X3 HYBRID 20K',
        'x3 30k'       => 'X3 HYBRID 30K',
    ];

    /**
     * Normalise "к" / "кВт" / "квт" / "kw" → "k" and collapse spaces.
     * Works on lowercase input.
     */
    private function normalisePower(string $s): string
    {
        // "6квт" / "6 квт" / "6kw" → "6k"
        $s = preg_replace('/(\d+)\s*(?:квт|kw|квт\.)/ui', '$1k', $s);
        // Cyrillic "к" used as kilo after a digit: "6к" → "6k"
        $s = preg_replace('/(\d+)\s*к\b/ui', '$1k', $s);
        // Cyrillic "г" used as "G" in model suffix: "г4" → "g4"
        $s = preg_replace('/г(\d)/ui', 'g$1', $s);
        return preg_replace('/\s+/', ' ', trim($s));
    }

    /**
     * Return true if the normalised query looks like a product/model mention.
     * Triggers on:  brand keywords  OR  (number + power unit)  OR  model-like tokens.
     */
    private function looksLikeProduct(string $norm): bool
    {
        // Known brand / series keywords (all lowercase)
        $brands = [
            'boost', 'solax', 'hybrid', 'inverter', 'інвертор',
            'mini', 'fit', 'growatt', 'deye', 'goodwe', 'solis',
            'pylontech', 'byd', 'huawei', 'sofar', 'sungrow',
        ];
        foreach ($brands as $b) {
            if (str_contains($norm, $b)) return true;
        }

        // Number followed by power unit (k / kw / квт)
        if (preg_match('/\d+\s*k(?:w|вт)?\b/i', $norm)) return true;

        // Model-code pattern: letter+digit run like "x1", "se10", "mg3"
        if (preg_match('/\b[a-z]{1,3}\d+\b/i', $norm)) return true;

        return false;
    }

    /**
     * Detect amoCRM search intent.
     *
     * Triggers when:
     *   A) "амо/amo" + any search verb  (explicit AMO reference)
     *   B) search verb + uppercase product token  (e.g. "знайди X1 BOOST 6K G4")
     *   C) search verb + fuzzy product signal     (e.g. "де boost 6к г4")
     */
    private function isAmoSearch(string $lq): bool
    {
        $norm = $this->normalisePower(
            preg_replace('/[^\p{L}\p{N}\s]/u', ' ', mb_strtolower($lq))
        );

        $hasAmo  = (bool) preg_match('/\bам[оo]\b|\bamo\b/ui', $norm);

        $verbs   = [
            'знайди', 'знайт', 'пошук',
            'покажи', 'показ',
            'хто', 'де',
            'є', 'був', 'була',
            'згадується', 'згадуєт',
            'писав', 'написав', 'пишуть', 'писали',
            'search',
        ];
        $hasVerb = false;
        foreach ($verbs as $v) {
            if (str_contains($norm, $v)) { $hasVerb = true; break; }
        }

        // A: explicit AMO + verb
        if ($hasAmo && $hasVerb) return true;

        // B: verb + strict uppercase product token
        if ($hasVerb && preg_match('/\b[A-Z][A-Z0-9\-]{1,}\s+[A-Z0-9]/', $lq)) return true;

        // C: verb + fuzzy product signal (brand keyword / power unit / model code)
        if ($hasVerb && $this->looksLikeProduct($norm)) return true;

        return false;
    }

    /**
     * Strip intent words, normalise, resolve alias, return canonical search string.
     */
    private function extractAmoQuery(string $q): string
    {
        $noise = [
            '/\bв\s+(?:амо|amo)\b\s*/ui',
            '/\b(?:знайди|знайт\w*|пошук\w*)\b\s*/ui',
            '/\b(?:покажи|показ\w*)\b\s*/ui',
            '/\bхто\s+(?:і\s+)?де\b\s*/ui',
            '/\bхто\b\s*/ui',
            '/\b(?:де|там)\b\s*/ui',
            '/\b(?:писав|написав|пишуть|писали|написали)\b\s*/ui',
            '/\b(?:згадується|згадуєт\w*)\b\s*/ui',
            '/\bв\s+(?:якому|якій)\s+проект[іиу]\b\s*/ui',
            '/\bпроект[іиу]?\b\s*/ui',
            '/\b(?:є|був|була|були)\b\s*/ui',
            '/\bв\s+обладнанн[іи]\b\s*/ui',
            '/\bобладнанн[іи]\b\s*/ui',
            '/\b(?:інвертор|инвертор)\b\s*/ui',
            '/\b(?:search|find|show)\b\s*/ui',
            '/\b(?:amo|амо)\b\s*/ui',
            // Strip "клієнта/замовника" prefix before name
            '/\bклієнта?\b\s*/ui',
            '/\bзамовника?\b\s*/ui',
            // Strip tail phrases like "і скажи скільки він ще винен"
            '/\s+і\s+скажи\s+.+$/sui',
            '/\s+і\s+покажи\s+.+$/sui',
            '/\s+і\s+скільки\s+.+$/sui',
        ];

        $result = $q;
        foreach ($noise as $p) {
            $result = preg_replace($p, ' ', $result);
        }
        $result = trim(preg_replace('/\s{2,}/', ' ', $result));

        // ── Alias lookup on normalised lowercase result ───────────────────────
        $normResult = $this->normalisePower(mb_strtolower($result));
        foreach (self::PRODUCT_ALIASES as $fuzzy => $canonical) {
            if (str_contains($normResult, $fuzzy)) {
                Log::debug('AI amo-search: alias matched', ['fuzzy' => $fuzzy, 'canonical' => $canonical]);
                return $canonical;
            }
        }

        // ── Good literal result ───────────────────────────────────────────────
        if (mb_strlen($result) >= 2) {
            Log::debug('AI amo-search: extracted', ['raw' => $q, 'result' => $result]);
            return $result;
        }

        // ── Fallback 1: longest UPPERCASE token run ───────────────────────────
        if (preg_match_all('/\b[A-Z][A-Z0-9\-]{1,}(?:\s+[A-Z0-9][A-Z0-9\-]*)+/u', $q, $m)) {
            $best = collect($m[0])->sortByDesc(fn($s) => mb_strlen($s))->first();
            if ($best) {
                Log::debug('AI amo-search: uppercase fallback', ['result' => $best]);
                return $best;
            }
        }

        // ── Fallback 2: last 4 words ──────────────────────────────────────────
        $last = implode(' ', array_slice(preg_split('/\s+/u', trim($q)), -4));
        Log::debug('AI amo-search: last-words fallback', ['result' => $last]);
        return $last ?: $q;
    }

    private function formatAmoResults(array $results, string $query): string
    {
        if (empty($results)) {
            return "❌ В amoCRM нічого не знайдено по запиту «{$query}»";
        }

        $n    = count($results);
        $noun = match(true) {
            $n === 1          => 'проект',
            $n >= 2 && $n < 5 => 'проекти',
            default           => 'проектів',
        };

        $lines = ["🔎 Знайдено {$n} {$noun} з «{$query}»:"];

        foreach ($results as $i => $r) {
            $lines[] = '';
            $lines[] = ($i + 1) . '. 🤝 ' . ($r['entity_name'] ?? '—');

            if (!empty($r['client']))  $lines[] = "👤 Клієнт: {$r['client']}";
            if (!empty($r['author']))  $lines[] = "🧑‍💼 Менеджер: {$r['author']}";
            if (!empty($r['text']))    $lines[] = "📦 Контекст: «{$r['text']}»";
            if (!empty($r['date']))    $lines[] = "📅 {$r['date']}";
        }

        return implode("\n", $lines);
    }

    // ── Stock helpers (used by amo-search step) ───────────────────────────────

    /**
     * Look up solarglass_stock by item_name / item_code.
     * Returns total qty and per-item breakdown.
     */
    private function getStockInfo(string $query): array
    {
        try {
            $rows = DB::table('solarglass_stock')
                ->where(function ($q) use ($query) {
                    $q->where('item_name', 'like', "%{$query}%")
                      ->orWhere('item_code', 'like', "%{$query}%");
                })
                ->get(['item_name', 'item_code', 'qty']);

            if ($rows->isEmpty()) {
                return ['found' => false, 'qty' => 0, 'items' => []];
            }

            $total = (int) $rows->sum('qty');
            $items = $rows->map(fn($r) => [
                'name' => $r->item_name,
                'code' => $r->item_code,
                'qty'  => (int) $r->qty,
            ])->values()->toArray();

            return ['found' => true, 'qty' => $total, 'items' => $items];
        } catch (\Throwable $e) {
            Log::warning('AI amo-search: stock lookup failed', ['err' => $e->getMessage()]);
            return ['found' => false, 'qty' => 0, 'items' => []];
        }
    }

    /**
     * Try to extract a required quantity from amo note texts.
     * Looks for patterns like "16 шт", "2шт", "потрібно 3 шт".
     * Returns null if no quantity found.
     */
    private function extractRequiredQty(array $amoHits): ?int
    {
        foreach ($amoHits as $hit) {
            $text = $hit['text'] ?? '';
            if (!$text) continue;

            // "16 шт", "16шт", "16 штук"
            if (preg_match('/(\d+)\s*шт/ui', $text, $m)) {
                $qty = (int) $m[1];
                if ($qty > 0 && $qty < 1000) return $qty; // sanity check
            }
        }
        return null;
    }

    /**
     * Format the stock section appended after amo results.
     */
    private function formatStockSection(array $stock, ?int $needed): string
    {
        $lines = ['', '📦 Склад:'];

        if (!$stock['found']) {
            $lines[] = '❌ На складі відсутній';
            return implode("\n", $lines);
        }

        $available = $stock['qty'];
        $items     = $stock['items'];

        // Show per-SKU breakdown if multiple rows matched
        if (count($items) > 1) {
            foreach ($items as $item) {
                $lines[] = "• {$item['name']}: {$item['qty']} шт";
            }
            $lines[] = "Разом: {$available} шт";
        } else {
            $lines[] = "✅ В наявності: {$available} шт";
        }

        if ($needed !== null) {
            $diff = $available - $needed;
            $lines[] = "📋 Потрібно: {$needed} шт";
            if ($diff >= 0) {
                $lines[] = "✅ Достатньо для реалізації";
            } else {
                $lines[] = "❌ Не вистачає: " . abs($diff) . ' шт';
                $lines[] = '⚠️ Рекомендація: дозамовити';
            }
        }

        return implode("\n", $lines);
    }

    // =========================================================================
    // Quick SQL patterns — all DB-backed, instant, never touch AI
    // =========================================================================

    private function quickAnswer(string $q, array $context): ?string
    {
        $lq  = mb_strtolower($q);
        $orig = $q;

        // ── Clarification follow-up ───────────────────────────────────────────
        // If there's a pending clarification in cache and the message looks like
        // a selection ("1", "другий", phone snippet, name fragment) → resolve it.
        $clarKey = 'ai_clarification_' . (auth()->id() ?? 'anon');
        $clarCtx = Cache::get($clarKey);

        if ($clarCtx && !empty($clarCtx['candidates'])) {
            if ($this->looksLikeClarificationResponse($lq)) {
                $resolved = $this->resolveClarification($lq, $clarCtx);

                if ($resolved !== null) {
                    Cache::forget($clarKey);
                    Log::channel('ai')->info('AI clarification resolved', [
                        'uid'        => auth()->id(),
                        'input'      => mb_substr($lq, 0, 100),
                        'query_type' => $clarCtx['query_type'] ?? '?',
                        'original_q' => mb_substr($clarCtx['original_q'] ?? '', 0, 100),
                    ]);
                    return $resolved;
                }

                // Unclear follow-up — show list again with reminder
                return $this->buildClarificationPrompt($clarCtx['candidates'], $clarCtx['original_q'])
                    . "\n\n_Напишіть номер (1, 2, 3…) або уточніть телефон / рядок з імені._";
            }
        }

        // ── Client / project lookup by name ───────────────────────────────────
        // Triggers when question clearly targets a specific client or project.
        // Two signal groups:
        //   A) Explicit client keywords: "клієнт", "замовник", "знайди", "пошукай"
        //   B) Project-info keywords + preposition + potential name
        $isExplicitClient = $this->matches($lq, ['клієнт', 'замовник', 'знайди', 'пошукай']);
        $isProjectInfoQ   = $this->matches($lq, ['інвертор', 'аванс', 'яка сума', 'що по'])
            && (bool) preg_match('/\b(?:у|в|для|по)\s+[а-яіїєґА-ЯІЇЄҐ]{3,}/u', $q);
        // C) Debt/financial keywords followed directly by a name (no preposition needed)
        // e.g. "скільки винен Бен", "аванс Бен", "борг Коліснику"
        $isDebtByName     = $this->matches($lq, ['винен', 'винна', 'аванс', 'борг', 'заборгованість'])
            && (bool) preg_match('/(?:винен|винна|аванс|борг|заборгованість)\s+([А-ЯІЇЄҐа-яіїєґ]{3,})/ui', $q);

        if ($isExplicitClient || $isProjectInfoQ || $isDebtByName) {
            $clientName = $this->extractClientName($q);
            if ($clientName) {
                $clientResult = $this->clientProjectInfo($clientName, $lq, $clarKey);
                if ($clientResult !== null) return $clientResult;
            }
        }

        // ── Wallet balances ───────────────────────────────────────────────────
        // Exclude client-debt context so "скільки грошей винні клієнти" doesn't match here
        $isClientDebtQ = $this->matches($lq, ['винн', 'борг', 'заборгован', 'не оплатил', 'не доплатил', 'клієнт']);
        if (!$isClientDebtQ && $this->matches($lq, ['скільки грошей', 'баланс гаманц', 'залишок гаманц', 'скільки коштів', 'гроші на рахунк', 'скільки на рахунк', 'баланс рахунк'])) {
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

        // ── Salary — unpaid workers ───────────────────────────────────────────
        if ($this->matches($lq, ['хто не отримав', 'хто без зарплати', 'хто ще не отримав', 'невиплачені співроб', 'хто не отримал', 'хто ще не заробив'])) {
            return $this->unpaidWorkers();
        }

        // ── Salary — pending total / "скільки я винен" ────────────────────────
        if ($this->matches($lq, ['скільки я винен', 'скільки треба виплатити', 'загальна зарплата', 'загальна зп', 'загальне нарахування', 'скільки винен співроб', 'скільки до виплати'])) {
            return $this->salaryPendingTotal();
        }

        // ── Salary — by role/group ────────────────────────────────────────────
        if ($this->matches($lq, ['зп монтаж', 'зарплата монтаж', 'зп електрик', 'зарплата електрик', 'зп прораб', 'зарплата прораб', 'нарахування монтаж', 'нарахування електрик', 'винен монтаж', 'винен електрик', 'скільки монтажникам', 'скільки електрикам'])) {
            $role = $this->extractSalaryRole($lq);
            return $this->salaryByRole($role);
        }

        // ── Salary by worker name ─────────────────────────────────────────────
        if ($this->matches($lq, ['зарплата', 'зарплатня', 'нарахування', 'заробіток', 'ставка'])) {
            $nameRaw = $this->extractPersonName($orig) ?? $this->extractPersonName($lq);

            if ($nameRaw) {
                // Collect all known staff names from both tables
                $allNames = DB::table('salary_rules')->pluck('staff_name')
                    ->merge(DB::table('salary_accruals')->distinct()->pluck('staff_name'))
                    ->unique()->values()->all();

                $matches = $this->fuzzyFindStaff($nameRaw, $allNames);

                // Ambiguous — show candidates
                if (count($matches) > 1) {
                    $list = implode("\n", array_map(fn($n) => "• {$n}", $matches));
                    return "💰 Знайдено кілька співробітників. Уточніть кого маєте на увазі:\n{$list}";
                }

                // Not found
                if (empty($matches)) {
                    return "💰 Співробітника «{$nameRaw}» не знайдено. Перевір написання або запитай список зарплат.";
                }

                // Exactly one match
                $resolvedName = $matches[0];
                $normalizedResolved = $this->normalizeUkr($resolvedName);
                $lines = [];

                $rules = DB::table('salary_rules')->get()
                    ->filter(fn($r) => $this->normalizeUkr($r->staff_name) === $normalizedResolved);

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

                $accruals = DB::table('salary_accruals')
                    ->selectRaw('staff_name, currency, status, SUM(amount) as total, COUNT(*) as cnt')
                    ->groupBy('staff_name', 'currency', 'status')
                    ->get()
                    ->filter(fn($r) => $this->normalizeUkr($r->staff_name) === $normalizedResolved);

                foreach ($accruals as $r) {
                    $total       = number_format((float)$r->total, 2, '.', ' ');
                    $statusLabel = $r->status === 'paid' ? '✅ виплачено' : '⏳ очікує виплати';
                    $lines[]     = "💸 Нараховано: {$total} {$r->currency} ({$statusLabel}, {$r->cnt} записів)";
                }

                if (empty($lines)) {
                    return "💰 По {$resolvedName} записів не знайдено.";
                }
                return "💰 **Зарплата — {$resolvedName}:**\n" . implode("\n", $lines);
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

        // ── Client debt (remaining payments) ──────────────────────────────────
        if ($this->matches($lq, [
            'заборгованість клієнт', 'борг клієнт', 'скільки винні клієнт', 'залишок оплат',
            'залишок по проект', 'скільки не оплатили', 'не доплатили', 'остаток оплат',
            'скільки ще повинні', 'скільки грошей винн', 'скільки грошей клієнт',
            'скільки нам винн', 'клієнти винн', 'скільки боргу', 'загальний борг',
        ])) {
            $financeStageIds = array_filter(
                (array) config('services.amocrm.finance_stage_ids', []),
                fn ($id) => is_numeric($id) && (int) $id > 0
            );
            $rows = DB::table('sales_projects as sp')
                ->join('amo_complectation_projects as ac', 'ac.wallet_project_id', '=', 'sp.id')
                ->whereIn('ac.status_id', $financeStageIds)
                ->where('sp.status', '!=', 'completed')
                ->select('sp.client_name', 'sp.total_amount', 'sp.currency', 'ac.raw_payload')
                ->get();

            if ($rows->isEmpty()) return "✅ Активних боргів не знайдено.";

            $totalSum = 0.0;
            $paidSum  = 0.0;
            $debtRows = [];
            foreach ($rows as $r) {
                $total   = (float) ($r->total_amount ?? 0);
                $prepaid = 0.0;
                if (!empty($r->raw_payload)) {
                    $payload = json_decode($r->raw_payload, true);
                    foreach ($payload['custom_fields_values'] ?? [] as $field) {
                        if (($field['field_name'] ?? '') === 'Предоплата, $') {
                            $prepaid = max(0.0, (float) ($field['values'][0]['value'] ?? 0));
                            break;
                        }
                    }
                }
                $debt = max(0.0, $total - $prepaid);
                $totalSum += $total;
                $paidSum  += $prepaid;
                if ($debt > 0) {
                    $debtRows[] = ['name' => $r->client_name, 'debt' => $debt, 'total' => $total, 'paid' => $prepaid];
                }
            }
            usort($debtRows, fn ($a, $b) => $b['debt'] <=> $a['debt']);

            $debtSum  = $totalSum - $paidSum;
            $pct      = $totalSum > 0 ? round($paidSum / $totalSum * 100) : 0;
            $lines = [];
            foreach ($debtRows as $d) {
                $debt  = number_format($d['debt'], 0, '.', ' ');
                $total = number_format($d['total'], 0, '.', ' ');
                $lines[] = "• {$d['name']} — {$debt} $ (з {$total} $)";
            }
            $totalFmt = number_format($totalSum, 0, '.', ' ');
            $paidFmt  = number_format($paidSum,  0, '.', ' ');
            $debtFmt  = number_format($debtSum,  0, '.', ' ');
            $cnt      = count($debtRows);
            return "💰 **Заборгованість клієнтів (активні проекти, {$cnt} боржників):**\n"
                . "Загальна сума: {$totalFmt} $\nОплачено: {$paidFmt} $ ({$pct}%)\n**Залишок: {$debtFmt} $**\n\n"
                . implode("\n", $lines);
        }

        // ── Equipment gap / what to order ─────────────────────────────────────
        if ($this->matches($lq, [
            'яке обладнання потрібн', 'чого не вистачає', 'що дозамовити', 'що замовити',
            'не вистачає для проект', 'комплектація обладнання', 'обладнання для комплектац',
            'перевір склад', 'аналіз складу', 'що треба замовит', 'нестача обладнання',
            'яке обладнання відсутн', 'що на складі не вистачає', 'замовити обладнання',
            'обладнання для реаліз', 'потрібне для реаліз', 'потрібно для реаліз',
            'що потрібно замовити', 'які матеріали', 'яке обладнання треба',
            'обладнання не вистачає', 'що відсутнє на складі',
            'по обладнанню', 'обладнання для проект', 'обладнання для завершен',
            'статус обладнання', 'обладнання проект', 'що з обладнанням',
        ])) {
            return $this->equipmentGapReport();
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

    /**
     * Route a detected intent to the appropriate data handler.
     * Returns null if the intent cannot be resolved to a data answer.
     */
    private function dispatchByIntent(string $intent, string $question, array $context = []): ?string
    {
        return match ($intent) {
            'wallet_balance'          => $this->quickAnswer('скільки грошей', $context),
            'stock_balance'           => $this->quickAnswer('залишок на складі', $context),
            'stock_shortage',
            'equipment_gap'           => $this->equipmentGapReport(),
            'expenses'                => $this->monthlyExpenses(now()->format('Y-m')),
            'salary'                  => $this->quickAnswer('зарплата', $context),
            'salary_total'            => $this->salaryPendingTotal(),
            'salary_pending_total'    => $this->salaryPendingTotal(),
            'salary_by_role'          => $this->salaryByRole($this->extractSalaryRole($question)),
            'salary_pending_by_role'  => $this->salaryByRole($this->extractSalaryRole($question), pendingOnly: true),
            'unpaid_workers'          => $this->unpaidWorkers(),
            'defects'                 => $this->quickAnswer('недолік', $context),
            'client_debt'             => $this->quickAnswer('заборгованість клієнт', $context),
            'projects'                => $this->quickAnswer('активні проекти', $context),
            'reclamations'            => $this->quickAnswer('рекламац', $context),
            'services'                => $this->quickAnswer('сервісні заявки', $context),
            default                   => null,
        };
    }

    /** Map role keyword → staff_group DB value */
    private const ROLE_MAP = [
        'монтаж'    => 'installation_team',
        'монтажник' => 'installation_team',
        'монтажнік' => 'installation_team',
        'монтера'   => 'installation_team',
        'електрик'  => 'electrician',
        'електрікам'=> 'electrician',
        'прораб'    => 'foreman',
        'форман'    => 'foreman',
        'бухгалтер' => 'accountant',
    ];

    private const ROLE_LABELS = [
        'installation_team' => 'Монтажники',
        'electrician'       => 'Електрики',
        'foreman'           => 'Прораби',
        'accountant'        => 'Бухгалтери',
    ];

    /** Extract DB staff_group from question text */
    private function extractSalaryRole(string $lq): ?string
    {
        foreach (self::ROLE_MAP as $keyword => $group) {
            if (str_contains($lq, $keyword)) {
                return $group;
            }
        }
        return null;
    }

    /**
     * Salary breakdown by role/group.
     * If $role is null — shows all groups.
     * If $pendingOnly — only status='pending'.
     */
    private function salaryByRole(?string $role, bool $pendingOnly = false): string
    {
        $query = DB::table('salary_accruals')
            ->selectRaw('staff_group, staff_name, currency, status, SUM(amount) as total')
            ->groupBy('staff_group', 'staff_name', 'currency', 'status');

        if ($role) {
            $query->where('staff_group', $role);
        }
        if ($pendingOnly) {
            $query->where('status', 'pending');
        }

        $rows = $query->orderBy('staff_group')->orderBy('staff_name')->get();

        if ($rows->isEmpty()) {
            $label = $role ? (self::ROLE_LABELS[$role] ?? $role) : 'співробітникам';
            $pend  = $pendingOnly ? ' (невиплачені)' : '';
            return "💰 Нарахувань{$pend} для «{$label}» не знайдено.";
        }

        // Group by staff_group for display
        $byGroup  = [];
        $totals   = [];
        $currency = 'грн';

        foreach ($rows as $r) {
            $g = $r->staff_group ?? 'other';
            $byGroup[$g][$r->staff_name] = ($byGroup[$g][$r->staff_name] ?? 0) + (float)$r->total;
            $totals[$g] = ($totals[$g] ?? 0) + (float)$r->total;
            $currency   = $r->currency ?? $currency;
        }

        $header = $pendingOnly ? '💰 **Невиплачена зарплата' : '💰 **Нараховано';
        if ($role) {
            $header .= ' — ' . (self::ROLE_LABELS[$role] ?? $role);
        }
        $header .= ':**';

        $lines = [];
        foreach ($byGroup as $group => $people) {
            $groupLabel = self::ROLE_LABELS[$group] ?? $group;
            if (!$role && count($byGroup) > 1) {
                $lines[] = "**{$groupLabel}:**";
            }
            foreach ($people as $name => $amount) {
                $fmt     = number_format($amount, 0, '.', ' ');
                $lines[] = "  • {$name} — {$fmt} {$currency}";
            }
            if (!$role && count($byGroup) > 1) {
                $groupFmt = number_format($totals[$group], 0, '.', ' ');
                $lines[]  = "  Разом: {$groupFmt} {$currency}";
            }
        }

        $grandTotal = array_sum($totals);
        $totalFmt   = number_format($grandTotal, 0, '.', ' ');
        $lines[]    = "\n**Загалом: {$totalFmt} {$currency}**";

        return $header . "\n" . implode("\n", $lines);
    }

    /**
     * Total pending (unpaid) salary across all groups.
     * If nothing pending — shows total paid as reference.
     */
    private function salaryPendingTotal(): string
    {
        $pending = DB::table('salary_accruals')
            ->where('status', 'pending')
            ->selectRaw('staff_group, currency, SUM(amount) as total')
            ->groupBy('staff_group', 'currency')
            ->get();

        if ($pending->isEmpty()) {
            // No pending — show paid totals for context
            $paid = DB::table('salary_accruals')
                ->where('status', 'paid')
                ->selectRaw('staff_group, currency, SUM(amount) as total')
                ->groupBy('staff_group', 'currency')
                ->get();

            if ($paid->isEmpty()) {
                return "💰 Нарахувань не знайдено.";
            }

            $lines = [];
            $grand = 0.0;
            $cur   = 'грн';
            foreach ($paid as $r) {
                $label  = self::ROLE_LABELS[$r->staff_group] ?? ($r->staff_group ?? '?');
                $fmt    = number_format((float)$r->total, 0, '.', ' ');
                $lines[] = "• {$label}: {$fmt} {$r->currency}";
                $grand  += (float)$r->total;
                $cur     = $r->currency;
            }
            $totalFmt = number_format($grand, 0, '.', ' ');
            return "✅ **Невиплачених нарахувань немає.**\n\nДовідково — всього виплачено:\n"
                . implode("\n", $lines)
                . "\n\n**Загалом: {$totalFmt} {$cur}**";
        }

        $lines = [];
        $grand = 0.0;
        $cur   = 'грн';
        foreach ($pending as $r) {
            $label  = self::ROLE_LABELS[$r->staff_group] ?? ($r->staff_group ?? '?');
            $fmt    = number_format((float)$r->total, 0, '.', ' ');
            $lines[] = "• {$label}: {$fmt} {$r->currency}";
            $grand  += (float)$r->total;
            $cur     = $r->currency;
        }
        $totalFmt = number_format($grand, 0, '.', ' ');
        return "💸 **Невиплачена зарплата (по групах):**\n"
            . implode("\n", $lines)
            . "\n\n**До виплати: {$totalFmt} {$cur}**";
    }

    /**
     * List of workers with pending (unpaid) salary.
     */
    private function unpaidWorkers(): string
    {
        $rows = DB::table('salary_accruals')
            ->where('status', 'pending')
            ->selectRaw('staff_name, staff_group, currency, SUM(amount) as total')
            ->groupBy('staff_name', 'staff_group', 'currency')
            ->orderBy('staff_group')
            ->orderByDesc('total')
            ->get();

        if ($rows->isEmpty()) {
            $total = DB::table('salary_accruals')->where('status', 'paid')->count();
            return "✅ **Всі співробітники отримали зарплату.** (виплачено {$total} записів)";
        }

        $lines = [];
        $grand = 0.0;
        $cur   = 'грн';
        foreach ($rows as $r) {
            $label  = self::ROLE_LABELS[$r->staff_group] ?? '';
            $badge  = $label ? " ({$label})" : '';
            $fmt    = number_format((float)$r->total, 0, '.', ' ');
            $lines[] = "• {$r->staff_name}{$badge} — {$fmt} {$r->currency}";
            $grand  += (float)$r->total;
            $cur     = $r->currency;
        }
        $cnt      = $rows->count();
        $totalFmt = number_format($grand, 0, '.', ' ');
        return "⚠️ **Не отримали зарплату ({$cnt} осіб):**\n"
            . implode("\n", $lines)
            . "\n\n**Разом до виплати: {$totalFmt} {$cur}**";
    }

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
     * Analyse equipment needed for active комплектація projects vs stock.
     * Stages: Частково оплатив (38556547), Комплектація (69586234), Очікування доставки (38556550).
     */
    private function equipmentGapReport(): string
    {
        $stageIds = [38556547, 69586234, 38556550];

        $projects = DB::table('sales_projects as sp')
            ->join('amo_complectation_projects as ac', 'ac.wallet_project_id', '=', 'sp.id')
            ->whereIn('ac.status_id', $stageIds)
            ->where('sp.status', '!=', 'completed')
            ->select(
                'sp.client_name', 'sp.panel_name', 'sp.panel_qty',
                'sp.inverter', 'sp.battery_name', 'sp.battery_qty',
                'sp.delivered_panels', 'sp.delivered_inverter', 'sp.delivered_battery'
            )
            ->get();

        if ($projects->isEmpty()) {
            return "✅ Немає активних проектів на етапах комплектації / очікування доставки.";
        }

        $stock = DB::table('solarglass_stock')
            ->where('qty', '>', 0)
            ->get(['item_name', 'qty'])
            ->toArray();

        // ── Aggregate needed (not yet delivered) ─────────────────────────────
        $panelsNeeded    = [];
        $invertersNeeded = [];
        $batteriesNeeded = [];

        foreach ($projects as $p) {
            // Panels
            $panelName = $this->stripEquipQty(trim((string) ($p->panel_name ?? '')));
            $panelQty  = max(0, (int) ($p->panel_qty ?? 0));
            $delivered = max(0, (int) ($p->delivered_panels ?? 0));
            if ($panelName && $panelName !== '-' && $panelQty > $delivered) {
                $key = $this->normalizeEquipName($panelName);
                $panelsNeeded[$key] = ($panelsNeeded[$key] ?? ['orig' => $panelName, 'qty' => 0]);
                $panelsNeeded[$key]['qty'] += ($panelQty - $delivered);
            }

            // Inverter (1 per project)
            $invName = trim((string) ($p->inverter ?? ''));
            if ($invName && $invName !== '-' && !(int) ($p->delivered_inverter ?? 0)) {
                $key = $this->normalizeEquipName($invName);
                $invertersNeeded[$key] = ($invertersNeeded[$key] ?? ['orig' => $invName, 'qty' => 0]);
                $invertersNeeded[$key]['qty']++;
            }

            // Batteries
            $batName = $this->stripEquipQty(trim((string) ($p->battery_name ?? '')));
            $batQty  = max(0, (int) ($p->battery_qty ?? 0));
            if ($batName && $batName !== '-' && $batQty > 0 && trim((string) ($p->delivered_battery ?? '')) === '') {
                $key = $this->normalizeEquipName($batName);
                $batteriesNeeded[$key] = ($batteriesNeeded[$key] ?? ['orig' => $batName, 'qty' => 0]);
                $batteriesNeeded[$key]['qty'] += $batQty;
            }
        }

        // ── Match against stock & build report ───────────────────────────────
        $sections = [];
        $toOrder  = [];

        $buildSection = function (array $needed, string $emoji, string $label) use ($stock, &$toOrder): string {
            if (empty($needed)) return '';
            $lines = [];
            foreach ($needed as $item) {
                $qty      = $item['qty'];
                $orig     = $item['orig'];
                $match    = $this->bestStockMatch($orig, $stock);
                $inStock  = $match ? (int) $match['qty'] : 0;
                $stockName = $match ? $match['item_name'] : null;

                if (!$match) {
                    $lines[]  = "  • {$orig}: потрібно {$qty} шт — ❓ не знайдено на складі";
                    $toOrder[] = "{$orig}: {$qty} шт (не знайдено)";
                } elseif ($inStock >= $qty) {
                    $spare    = $inStock - $qty;
                    $lines[]  = "  • {$orig}: потрібно {$qty} шт → склад {$inStock} шт ✅ (залишок {$spare})";
                } else {
                    $gap      = $qty - $inStock;
                    $lines[]  = "  • {$orig}: потрібно {$qty} шт → склад {$inStock} шт ❌ **ЗАМОВИТИ {$gap} шт**";
                    $toOrder[] = "{$orig}: {$gap} шт (є {$inStock}, потрібно {$qty})";
                }
            }
            return "{$emoji} **{$label}:**\n" . implode("\n", $lines);
        };

        $sections[] = $buildSection($panelsNeeded,    '☀️', 'Панелі');
        $sections[] = $buildSection($invertersNeeded, '⚡', 'Інвертори');
        $sections[] = $buildSection($batteriesNeeded, '🔋', 'Батареї');
        $sections   = array_filter($sections);

        $cnt = $projects->count();
        $header = "🔧 **Аналіз обладнання для комплектації ({$cnt} проектів):**\n";

        if (empty($sections)) {
            return $header . "Дані по обладнанню відсутні в проектах.";
        }

        $body = implode("\n\n", $sections);

        if (!empty($toOrder)) {
            $orderList = implode("\n", array_map(fn ($s) => "  • {$s}", $toOrder));
            $body .= "\n\n🛒 **Список для замовлення (" . count($toOrder) . " позиції):**\n{$orderList}";
        } else {
            $body .= "\n\n✅ Все необхідне обладнання є на складі.";
        }

        return $header . $body;
    }

    /**
     * Find the best matching stock item for a given equipment name.
     * Uses token overlap scoring — requires ≥55% of name tokens to match.
     */
    private function bestStockMatch(string $name, array $stock): ?array
    {
        $norm   = $this->normalizeEquipName($name);
        $tokens = $this->equipTokens($norm);
        if (empty($tokens)) return null;

        $bestScore = 0.0;
        $bestItem  = null;

        foreach ($stock as $item) {
            $stockNorm = $this->normalizeEquipName((string) ($item->item_name ?? $item['item_name'] ?? ''));
            $matched   = 0;
            foreach ($tokens as $t) {
                if (str_contains($stockNorm, $t)) $matched++;
            }
            $score = $matched / count($tokens);
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestItem  = (array) $item;
            }
        }

        return $bestScore >= 0.55 ? $bestItem : null;
    }

    /** Lowercase + keep only letters, digits, spaces */
    private function normalizeEquipName(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $s);
        $s = preg_replace('/\s+/', ' ', $s);
        return trim((string) $s);
    }

    /** Split into tokens ≥3 chars, remove pure numeric or unit noise */
    private function equipTokens(string $norm): array
    {
        $noise = ['шт', 'pcs', 'кw', 'kw', 'lv', 'hv', 'вт', 'the', 'для'];
        $tokens = [];
        foreach (explode(' ', $norm) as $t) {
            $t = trim($t);
            if (mb_strlen($t) < 3 || in_array($t, $noise, true)) continue;
            $tokens[] = $t;
        }
        return array_unique($tokens);
    }

    /** Strip quantity suffix like "- 5шт", "- 16шт.", "(10 шт)" from equipment name */
    private function stripEquipQty(string $s): string
    {
        $s = preg_replace('/[-–—]?\s*\d+\s*шт\.?/ui', '', $s);
        $s = preg_replace('/\(\s*\d+\s*шт\.?\s*\)/ui', '', $s);
        return trim((string) preg_replace('/[-–—\s]+$/', '', $s));
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
     * Extract person name from question.
     * Works with any case: "у Малінін", "у малініна", "зарплата кукуяки"
     */
    private function extractPersonName(string $q): ?string
    {
        // After prepositions: "у Малінін", "в малініна", "для кукуяки"
        if (preg_match('/(?:у|в|для|по)\s+([а-яіїєґА-ЯІЇЄҐ][а-яіїєґА-ЯІЇЄҐ\'-]{2,})/u', $q, $m)) {
            return $m[1];
        }
        // Keyword followed by name (any case)
        if (preg_match('/(?:зарплата|зарплатня|нарахування|заробіток)\s+([а-яіїєґА-ЯІЇЄҐ][а-яіїєґА-ЯІЇЄҐ\'-]{2,})/u', $q, $m)) {
            return $m[1];
        }
        // Lone word at end that looks like a surname (≥4 chars, after salary keyword in sentence)
        if (preg_match('/(?:зарплат|нарахуван|заробіток|ставк)\S*\s+.*?\s+([а-яіїєґА-ЯІЇЄҐ][а-яіїєґА-ЯІЇЄҐ\'-]{3,})$/u', $q, $m)) {
            return $m[1];
        }
        return null;
    }

    /**
     * Extract a client name from a question about a project.
     *
     * Handles:
     *   "знайди клієнта Петров Сергій"  → "Петров Сергій"
     *   "який інвертор у Петрова"        → "Петрова"
     *   "яка сума у Іваненка"            → "Іваненка"
     *   "що по Ткаченку"                 → "Ткаченку"
     */
    private function extractClientName(string $q): ?string
    {
        // Pattern A: after "клієнта/замовника" — capture 1-2 Ukrainian words
        if (preg_match('/(?:клієнта?|клієнту|замовника?)\s+([А-ЯІЇЄҐа-яіїєґ][А-ЯІЇЄҐа-яіїєґ\'-]{2,}(?:\s+[А-ЯІЇЄҐа-яіїєґ][А-ЯІЇЄҐа-яіїєґ\'-]{2,})?)/u', $q, $m)) {
            return trim($m[1]);
        }
        // Pattern B: "знайди/покажи/пошукай" + optional "клієнта" + uppercase-starting name
        if (preg_match('/(?:знайди|покажи|пошукай|пошук)\w*\s+(?:клієнта?\s+|замовника?\s+)?([А-ЯІЇЄҐ][А-ЯІЇЄҐа-яіїєґ\'-]{2,}(?:\s+[А-ЯІЇЄҐ][А-ЯІЇЄҐа-яіїєґ\'-]{2,})?)/u', $q, $m)) {
            return trim($m[1]);
        }
        // Pattern C: "знайди клієнта" + any-case name (lowercase input)
        if (preg_match('/(?:знайди|пошукай)\w*\s+(?:клієнта?\s+)?([а-яіїєґ][а-яіїєґ\'-]{3,}(?:\s+[а-яіїєґ][а-яіїєґ\'-]{3,})?)/u', mb_strtolower($q), $m)) {
            $word = trim($m[1]);
            $skip = ['клієнт', 'проект', 'замовник', 'інвертор', 'аванс', 'сума'];
            if (!in_array($word, $skip, true)) return $word;
        }
        // Pattern D: preposition "у/в/для/по" + Ukrainian word ≥3 chars total
        if (preg_match('/\b(?:у|в|для)\s+([А-ЯІЇЄҐа-яіїєґ][А-ЯІЇЄҐа-яіїєґ\'-]{2,})\b/u', $q, $m)) {
            $word = trim($m[1]);
            $stopWords = ['мене', 'тебе', 'нього', 'неї', 'нас', 'вас', 'яких',
                          'цьому', 'цього', 'якому', 'якій', 'якого', 'всіх'];
            if (!in_array(mb_strtolower($word), $stopWords, true)) {
                return $word;
            }
        }
        // Pattern E: "що по [word]" (not stock/finance context)
        if (preg_match('/що\s+(?:там\s+)?по\s+([А-ЯІЇЄҐа-яіїєґ][А-ЯІЇЄҐа-яіїєґ\'-]{3,})/u', $q, $m)) {
            $word = trim($m[1]);
            $skipPo = ['складу', 'складі', 'витратах', 'зарплаті', 'рахунках', 'проектах', 'обладнанню'];
            if (!in_array(mb_strtolower($word), $skipPo, true)) return $word;
        }
        // Pattern F: debt/financial keyword + name without preposition
        // e.g. "скільки винен Бен", "аванс Бен", "борг Коліснику"
        if (preg_match('/(?:винен|винна|аванс|борг|заборгованість)\s+([А-ЯІЇЄҐа-яіїєґ][А-ЯІЇЄҐа-яіїєґ\'-]{2,}(?:\s+[А-ЯІЇЄҐа-яіїєґ][А-ЯІЇЄҐа-яіїєґ\'-]{2,})?)/ui', $q, $m)) {
            return trim($m[1]);
        }
        return null;
    }

    /**
     * Look up a client project by name in sales_projects.
     *
     * Rules:
     *   0 results  → return null (pass to SQL agent)
     *   1 result   → return formatted answer immediately
     *   2+ results → clarification mode: store candidates in cache, return selection prompt
     */
    private function clientProjectInfo(string $rawName, string $lq, string $clarKey = ''): ?string
    {
        // Build search stem: strip common Ukrainian genitive endings
        // Note: SQLite LOWER() does not support Cyrillic — search without it
        $raw  = trim($rawName);
        $cap  = mb_strtoupper(mb_substr($raw, 0, 1)) . mb_substr($raw, 1);
        $norm = mb_strtolower($raw);
        // Genitive stem: strip trailing endings (Петрова→Петров, Іваненка→Іваненк)
        $stem = (string) preg_replace('/(?:ові|ого|ьої|ієї|єць)$/u', '', $cap);
        if ($stem === $cap) {
            $stem = (string) preg_replace('/[ауяієї]$/u', '', $cap);
        }
        if (mb_strlen($stem) < 3) $stem = $cap;

        try {
            $rows = DB::table('sales_projects')
                ->where(function ($q) use ($stem, $cap, $norm) {
                    $q->where('client_name', 'like', "%{$stem}%")
                      ->orWhere('client_name', 'like', "%{$cap}%")
                      ->orWhere('client_name', 'like', "%{$norm}%");
                })
                ->whereNull('cancelled_at')
                ->select([
                    'id', 'client_name', 'phone_number',
                    'total_amount', 'advance_amount', 'remaining_amount', 'currency',
                    'inverter', 'panel_name', 'panel_qty', 'battery_name', 'battery_qty',
                    'status', 'construction_status', 'created_at',
                ])
                ->orderByDesc('created_at')
                ->limit(5)
                ->get();
        } catch (\Throwable) {
            return null;
        }

        if ($rows->isEmpty()) return null;

        $queryType = $this->detectClientQueryType($lq);

        // ── Single result → answer immediately ───────────────────────────────
        if ($rows->count() === 1) {
            return $this->formatSingleClientAnswer($rows->first(), $queryType);
        }

        // ── Multiple results → clarification mode ────────────────────────────
        $candidates = $rows->map(fn($r) => (array) $r)->values()->toArray();

        if ($clarKey) {
            Cache::put($clarKey, [
                'candidates' => $candidates,
                'query_type' => $queryType,
                'original_q' => $lq,
            ], now()->addMinutes(10));
        }

        Log::channel('ai')->info('AI clarification_mode', [
            'uid'             => auth()->id(),
            'extracted_client'=> $rawName,
            'candidates_count'=> count($candidates),
            'query_type'      => $queryType,
            'original_q'      => mb_substr($lq, 0, 100),
            'resolved'        => false,
        ]);

        return $this->buildClarificationPrompt($candidates, $lq);
    }

    /**
     * Detect what type of info the user wants about a client project.
     */
    private function detectClientQueryType(string $lq): string
    {
        if ($this->matches($lq, ['інвертор']))                                 return 'inverter';
        if ($this->matches($lq, ['аванс', 'передопл', 'передплат', 'вніс', 'оплатив'])) return 'advance';
        if ($this->matches($lq, ['яка сума', 'скільки коштує', 'вартість', 'ціна', 'сума'])) return 'amount';
        if ($this->matches($lq, ['обладнання', 'панел', 'батарей', 'акб']))    return 'equipment';
        return 'full';
    }

    /**
     * Format the answer for a single matched project according to query type.
     */
    private function formatSingleClientAnswer(object $p, string $queryType): string
    {
        $statusMap = [
            'has_deficiencies'   => '❌ Є недоліки',
            'deficiencies_fixed' => '🟡 Виправлено',
            'salary_paid'        => '✅ Зарплата виплачена',
        ];
        $cur = $p->currency ?? '';

        $parts = match ($queryType) {
            'inverter' => [
                "⚡ Інвертор: " . ($p->inverter ?: '—'),
            ],
            'advance' => [
                "💵 Аванс: " . ($p->advance_amount ? number_format((float)$p->advance_amount, 0, '.', ' ') : '0') . " {$cur}",
                "📋 Залишок: " . ($p->remaining_amount ? number_format((float)$p->remaining_amount, 0, '.', ' ') : '—') . " {$cur}",
            ],
            'amount' => [
                "💰 Сума: " . ($p->total_amount ? number_format((float)$p->total_amount, 0, '.', ' ') : '—') . " {$cur}",
            ],
            'equipment' => array_filter([
                $p->inverter     ? "⚡ Інвертор: {$p->inverter}"                           : null,
                $p->panel_name   ? "☀️ Панелі: {$p->panel_name} × {$p->panel_qty}"        : null,
                $p->battery_name ? "🔋 АКБ: {$p->battery_name} × {$p->battery_qty}"       : null,
            ]),
            default => array_filter([
                $p->total_amount    ? "💰 Сума: " . number_format((float)$p->total_amount, 0, '.', ' ') . " {$cur}"   : null,
                $p->advance_amount  ? "💵 Аванс: " . number_format((float)$p->advance_amount, 0, '.', ' ')
                                    . " | Залишок: " . number_format((float)($p->remaining_amount ?? 0), 0, '.', ' ')
                                    . " {$cur}"                                                                         : null,
                $p->inverter        ? "⚡ Інвертор: {$p->inverter}"                        : null,
                $p->panel_name      ? "☀️ Панелі: {$p->panel_name} × {$p->panel_qty}"     : null,
                $p->battery_name    ? "🔋 АКБ: {$p->battery_name} × {$p->battery_qty}"    : null,
                $p->phone_number    ? "📞 Тел: {$p->phone_number}"                         : null,
                $p->construction_status
                    ? "📊 Стан: " . ($statusMap[$p->construction_status] ?? $p->construction_status) : null,
            ]),
        };

        $parts = array_values(array_filter($parts));
        if (empty($parts)) return "👤 **{$p->client_name}** — даних не знайдено.";

        return "👤 **{$p->client_name}**\n" . implode("\n", $parts);
    }

    /**
     * Build a numbered clarification prompt from candidate rows.
     */
    private function buildClarificationPrompt(array $candidates, string $originalQ): string
    {
        $lines = ["🔍 Знайдено кілька варіантів. Уточніть, будь ласка:\n"];

        foreach ($candidates as $i => $c) {
            $n    = $i + 1;
            $name = $c['client_name'] ?? '—';

            $hints = [];
            if (!empty($c['phone_number'])) {
                $hints[] = "тел. …" . mb_substr(preg_replace('/\D/', '', $c['phone_number'] ?? ''), -4);
            }
            if (!empty($c['inverter'])) {
                $hints[] = $c['inverter'];
            }
            if (!empty($c['total_amount'])) {
                $hints[] = number_format((float)$c['total_amount'], 0, '.', ' ') . ' ' . ($c['currency'] ?? '');
            }
            if (!empty($c['created_at'])) {
                $hints[] = "від " . mb_substr((string)$c['created_at'], 0, 10); // "2025-04-15"
            }
            if (!empty($c['id'])) {
                $hints[] = "ID#{$c['id']}";
            }

            $hint  = $hints ? ' — ' . implode(', ', $hints) : '';
            $lines[] = "{$n}. {$name}{$hint}";
        }

        $lines[] = "\nНапишіть номер або уточніть телефон / інвертор / суму.";
        return implode("\n", $lines);
    }

    /**
     * Return true if the message looks like a clarification selection response.
     * Triggers on: digit, ordinal words, phone fragment, short name hint.
     */
    private function looksLikeClarificationResponse(string $lq): bool
    {
        // Single digit or "перший"/"другий"/"третій" etc.
        if (preg_match('/^\s*\d\s*$/', $lq))                                  return true;
        if (preg_match('/\b(?:перш|друг|трет|четверт|п\'ят)[а-яіїє]*/u', $lq)) return true;
        // Short message ≤ 30 chars that has a digit or Cyrillic name token
        if (mb_strlen(trim($lq)) <= 30 && preg_match('/\d|[А-ЯІЇЄҐа-яіїєґ]{4,}/u', $lq)) return true;
        return false;
    }

    /**
     * Try to resolve a clarification: match the user's reply to one candidate.
     * Returns formatted answer on success, null if ambiguous.
     */
    private function resolveClarification(string $lq, array $ctx): ?string
    {
        $candidates = $ctx['candidates'];
        $queryType  = $ctx['query_type'] ?? 'full';
        $total      = count($candidates);

        // 1. Numeric index: "1", "2", "другий", "перший" …
        $idx = $this->parseSelectionIndex($lq, $total);
        if ($idx !== null) {
            return $this->formatSingleClientAnswer((object)$candidates[$idx], $queryType);
        }

        // 2. Phone digits fragment (≥4 consecutive digits)
        if (preg_match('/\d{4,}/', $lq, $dm)) {
            $fragment = $dm[0];
            foreach ($candidates as $c) {
                $digits = preg_replace('/\D/', '', $c['phone_number'] ?? '');
                if ($digits && str_contains($digits, $fragment)) {
                    return $this->formatSingleClientAnswer((object)$c, $queryType);
                }
            }
        }

        // 3. Name fragment ≥4 chars that matches exactly one candidate
        $words = array_filter(
            preg_split('/\s+/u', trim($lq)) ?: [],
            fn($w) => mb_strlen($w) >= 4
        );
        $matched = [];
        foreach ($candidates as $i => $c) {
            $cname = mb_strtolower($c['client_name'] ?? '');
            foreach ($words as $word) {
                if (str_contains($cname, mb_strtolower($word))) {
                    $matched[$i] = $c;
                    break;
                }
            }
        }
        if (count($matched) === 1) {
            return $this->formatSingleClientAnswer((object)reset($matched), $queryType);
        }

        return null;
    }

    /**
     * Parse ordinal / digit selection from user reply.
     * Returns 0-based index or null.
     */
    private function parseSelectionIndex(string $lq, int $total): ?int
    {
        // Plain digit
        if (preg_match('/^\s*(\d+)\s*$/', $lq, $m)) {
            $n = (int)$m[1];
            return ($n >= 1 && $n <= $total) ? $n - 1 : null;
        }
        // Digit inside a short phrase: "варіант 2", "номер 3", "2й"
        if (preg_match('/(?:варіант|номер|пункт|number)?\s*(\d+)/u', $lq, $m)) {
            $n = (int)$m[1];
            if ($n >= 1 && $n <= $total) return $n - 1;
        }
        // Ukrainian ordinals
        $ordinals = [
            'перш'  => 1, 'перша' => 1, 'перший' => 1, 'першого' => 1,
            'друг'  => 2, 'другий'=> 2, 'друга'  => 2, 'другого' => 2,
            'трет'  => 3, 'третій'=> 3, 'третя'  => 3, 'третього'=> 3,
            'четверт' => 4,
            'п\'ят'   => 5, 'пʼят' => 5,
        ];
        foreach ($ordinals as $word => $n) {
            if (str_contains($lq, $word) && $n <= $total) return $n - 1;
        }
        return null;
    }

    /**
     * Normalize Ukrainian text for fuzzy comparison:
     * lowercase, trim, і→и, ї→и, є→е, ґ→г
     */
    private function normalizeUkr(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = str_replace(['і', 'ї', 'є', 'ґ'], ['и', 'и', 'е', 'г'], $s);
        return $s;
    }

    /**
     * Fuzzy staff name search.
     *
     * Algorithm (per candidate name):
     *   1. Exact substring match on normalized text        → score 1.0
     *   2. Input is prefix/stem of any word in candidate  → score 0.9
     *   3. Levenshtein distance ≤ 2 on any word           → score 0.8
     *   4. similar_text() ≥ 70% on any word               → score by pct
     *
     * Returns canonical DB names that matched (sorted best-first, max 5).
     *
     * @param  string   $input   Raw user input (e.g. "малініна")
     * @param  string[] $names   List of canonical staff names from DB
     * @return string[]
     */
    private function fuzzyFindStaff(string $input, array $names): array
    {
        $normInput = $this->normalizeUkr($input);
        // Strip common case endings from input to get a stem for prefix matching
        // е.г. "малініна" → "малинин", "кукуяки" → "кукуяк"
        $stem = rtrim($normInput, 'аяуюоеиіїє');
        if (mb_strlen($stem) < 3) $stem = $normInput;

        $scored = [];

        foreach ($names as $name) {
            $normName  = $this->normalizeUkr($name);
            $bestScore = 0.0;

            // Check against whole name and each word separately
            $parts = array_filter(explode(' ', $normName), fn($p) => mb_strlen($p) >= 2);

            foreach ([$normName, ...$parts] as $part) {
                // 1. Exact substring
                if (str_contains($part, $normInput) || str_contains($normInput, $part)) {
                    $bestScore = max($bestScore, 1.0);
                    break;
                }

                // 2. Stem prefix
                if (mb_strlen($stem) >= 3 && str_contains($part, $stem)) {
                    $bestScore = max($bestScore, 0.9);
                }

                // 3. Levenshtein ≤ 2
                $lev = levenshtein($normInput, $part);
                if ($lev <= 2) {
                    $score = 1.0 - ($lev / max(mb_strlen($normInput), mb_strlen($part)));
                    $bestScore = max($bestScore, $score);
                }

                // 4. similar_text ≥ 70%
                similar_text($normInput, $part, $pct);
                if ($pct >= 70) {
                    $bestScore = max($bestScore, $pct / 100);
                }
            }

            if ($bestScore >= 0.7) {
                $scored[$name] = $bestScore;
            }
        }

        arsort($scored);
        return array_keys(array_slice($scored, 0, 5, true));
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

    /**
     * Check AI response for critical signals and fire notifications.
     * Called after a response is generated — never throws.
     */
    private function maybeNotify(?int $userId, string $intent, string $response): void
    {
        if (!$userId) return;

        try {
            // Unpaid salary alert
            if (in_array($intent, ['salary_pending_total', 'unpaid_workers', 'salary_pending_by_role'])
                && str_contains($response, 'До виплати:')
                && !str_contains($response, 'Невиплачених нарахувань немає')
            ) {
                // Extract amount from response
                preg_match('/До виплати:\s*([\d\s]+)/u', $response, $m);
                $amount = $m[1] ?? '';
                $this->notifications->send(
                    $userId,
                    '⚠️ Невиплачена зарплата',
                    trim($amount) ? "Залишок до виплати: {$amount} грн" : 'Є невиплачені нарахування',
                    'salary_alert'
                );
            }

            // Low stock / equipment gap alert
            if (in_array($intent, ['stock_shortage', 'equipment_gap'])
                && str_contains($response, 'ЗАМОВИТИ')
            ) {
                preg_match('/Список для замовлення \((\d+) позиці/u', $response, $m);
                $cnt = $m[1] ?? '';
                $this->notifications->send(
                    $userId,
                    '📦 Нестача обладнання',
                    $cnt ? "Потрібно замовити {$cnt} позицій" : 'Частина обладнання відсутня на складі',
                    'stock_alert'
                );
            }

            // Negative wallet balance alert
            if ($intent === 'wallet_balance' && str_contains($response, '⚠️')) {
                $this->notifications->send(
                    $userId,
                    '💰 Увага: від\'ємний баланс',
                    'Один або кілька рахунків мають від\'ємний залишок',
                    'finance_alert'
                );
            }
        } catch (\Throwable) {
            // Never fail because of notifications
        }
    }
}
