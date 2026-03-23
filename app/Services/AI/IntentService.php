<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Intent detection service.
 *
 * Pipeline:
 *   1. Normalize question (transliteration of slang, synonym expansion)
 *   2. Ollama (local LLM) — 2s timeout, few-shot prompt
 *   3. Keyword fallback — instant, no AI
 *
 * Returns one of the INTENTS constants or null (= pass to SQL agent).
 */
class IntentService
{
    public const INTENTS = [
        'stock_shortage',        // what to order, what's missing
        'stock_balance',         // what's in stock
        'wallet_balance',        // wallet / account balances
        'expenses',              // expenses / spending
        'salary',                // salary by person name
        'salary_total',          // total salary across all staff
        'salary_pending_total',  // total unpaid salary
        'salary_by_role',        // salary breakdown by role/group
        'salary_pending_by_role',// unpaid salary for specific role
        'unpaid_workers',        // who hasn't received salary
        'defects',               // defects / quality issues
        'projects',              // active projects
        'client_debt',           // client remaining payments
        'equipment_gap',         // equipment needed for projects
        'reclamations',          // complaints / reclamations
        'services',              // service requests
    ];

    // ─────────────────────────────────────────────────────────────────────────
    // Slang / synonym normalization
    // Maps informal Ukrainian/Russian words → canonical Ukrainian equivalents
    // so keyword matching works on normalized text too.
    // ─────────────────────────────────────────────────────────────────────────
    private const SYNONYM_MAP = [
        // money / balance
        'бабки'       => 'гроші',
        'бабло'       => 'гроші',
        'капуста'     => 'гроші',
        'бабосики'    => 'гроші',
        'грошики'     => 'гроші',
        'кеш'         => 'гроші',
        'cash'        => 'гроші',
        'money'       => 'гроші',
        'бюджет'      => 'баланс',
        'рахунки'     => 'рахунок',
        'рахунках'    => 'рахунок',
        // stock / warehouse
        'склад'       => 'склад',
        'складі'      => 'склад',
        'складу'      => 'склад',
        'warehouse'   => 'склад',
        'стоки'       => 'склад',
        'залишки'     => 'залишок на складі',
        // defects / problems
        'проблемн'    => 'недолік',
        'косяки'      => 'недолік',
        'косяк'       => 'недолік',
        'баги'        => 'дефект',
        'проблем'     => 'недолік',
        'косячн'      => 'недолік',
        // expenses
        'трати'       => 'витрати',
        'тратили'     => 'витратили',
        'затрати'     => 'витрати',
        'витрачали'   => 'витратили',
        // projects
        'обєкти'      => 'проекти',
        "об'єкти"     => 'проекти',
        'обʼєкти'     => 'проекти',
        'обєкт'       => 'проект',
        "об'єкт"      => 'проект',
        'стройки'     => 'проекти',
        'будови'      => 'проекти',
        // debt
        'должники'    => 'заборгованість',
        'боржники'    => 'заборгованість',
        'должні'      => 'винні',
        'должен'      => 'винні',
        'долг'        => 'борг',
        // salary
        'жалованье'   => 'зарплата',
        'жалування'   => 'зарплата',
        'платня'      => 'зарплата',
        'получка'     => 'зарплата',
        'получат'     => 'нарахування',
        // equipment
        'панельки'    => 'панелі',
        'инверторы'   => 'інвертори',
        'батарейки'   => 'батареї',
    ];

    // ─────────────────────────────────────────────────────────────────────────
    // Keyword map (runs on normalized question)
    // ─────────────────────────────────────────────────────────────────────────
    private const KEYWORD_MAP = [
        'wallet_balance'  => [
            'баланс', 'гаманц', 'скільки грошей', 'скільки коштів',
            'на рахунк', 'залишок рахун', 'скільки бабок', 'скільки бабла',
            'скільки кешу', 'по грошах', 'по грошам', 'який баланс',
        ],
        'stock_balance'   => [
            'на складі', 'залишок на склад', 'що є на склад', 'весь склад',
            'скільки панел', 'скільки батар', 'скільки інверт',
            'по складу', 'по складі', 'що на склад',
        ],
        'stock_shortage'  => [
            'не вистачає', 'дозамовит', 'замовити обладн', 'нестача',
            'що відсутнє', 'що замовити',
        ],
        'equipment_gap'   => [
            'обладнання потрібн', 'для реалізац', 'для комплектац',
            'потрібне для', 'потрібно для', 'що треба замовит',
            'по обладнанню', 'обладнання для проект', 'що з обладнанням',
            'обладнання проект', 'обладнання для завершен',
        ],
        'expenses'        => [
            'витрати', 'витратили', 'видатки', 'скільки витрат',
            'куди йдуть', 'на що витрачали', 'скільки потратили',
        ],
        'unpaid_workers'          => [
            'хто не отримав', 'хто без зарплати', 'хто ще не', 'невиплачені співроб',
        ],
        'salary_pending_total'    => [
            'скільки я винен', 'скільки треба виплатити', 'загальна зарплата',
            'загальна зп', 'скільки до виплати', 'скільки винен співроб',
        ],
        'salary_by_role'          => [
            'зп монтаж', 'зарплата монтаж', 'зп електрик', 'зарплата електрик',
            'зп прораб', 'нарахування монтаж', 'нарахування електрик',
            'скільки монтажникам', 'скільки електрикам',
        ],
        'salary_pending_by_role'  => [
            'винен монтаж', 'винен електрик', 'скільки монтажники не отримали',
        ],
        'salary'          => [
            'зарплат', 'нарахуван', 'заробіток', 'ставка',
            'виплати співроб', 'скільки платим', 'скільки отримуєт',
        ],
        'defects'         => [
            'недолік', 'дефект', 'брак', 'є недоліки', 'виправил',
            'проблемні проект', 'проблемні об', 'косяки на проект',
        ],
        'client_debt'     => [
            'заборгованість', 'борг клієнт', 'не оплатили', 'залишок оплат',
            'скільки винн', 'не доплатили', 'грошей винн', 'нам винн',
            'клієнти винн', 'скільки боргу', 'хто не заплатив',
        ],
        'projects'        => [
            'активні проекти', 'скільки проект', 'проекти в робот', 'pipeline',
            'скільки обєкт', "скільки об'єкт", 'скільки будов',
        ],
        'reclamations'    => [
            'рекламац', 'скарг', 'відкриті рекламац', 'рекламації',
        ],
        'services'        => [
            'сервісні заявки', 'заявки на сервіс', 'відкриті заявки',
        ],
    ];

    // ─────────────────────────────────────────────────────────────────────────
    // Few-shot prompt
    // ─────────────────────────────────────────────────────────────────────────
    private const PROMPT_TEMPLATE = <<<'PROMPT'
Ти AI аналітик ERP системи SolarGlass. Визнач intent запиту користувача.

Приклади:

Питання: скільки грошей на рахунках
Intent: wallet_balance

Питання: шо там по грошах
Intent: wallet_balance

Питання: скільки бабок у нас
Intent: wallet_balance

Питання: який баланс
Intent: wallet_balance

Питання: що на складі
Intent: stock_balance

Питання: шо по складу
Intent: stock_balance

Питання: залишки на складі
Intent: stock_balance

Питання: чого не вистачає для замовлення
Intent: stock_shortage

Питання: що треба дозамовити
Intent: stock_shortage

Питання: яке обладнання потрібне для проектів
Intent: equipment_gap

Питання: що там по обладнанню
Intent: equipment_gap

Питання: скільки витратили цього місяця
Intent: expenses

Питання: куди йдуть гроші
Intent: expenses

Питання: яка зарплата у Малініна
Intent: salary

Питання: зп монтажників
Intent: salary_by_role

Питання: скільки нарахували монтажникам
Intent: salary_by_role

Питання: зарплата електриків
Intent: salary_by_role

Питання: скільки я винен монтажникам
Intent: salary_pending_by_role

Питання: скільки треба виплатити
Intent: salary_pending_total

Питання: загальна заборгованість по зарплаті
Intent: salary_pending_total

Питання: скільки я винен співробітникам
Intent: salary_pending_total

Питання: хто не отримав зарплату
Intent: unpaid_workers

Питання: хто ще без виплати
Intent: unpaid_workers

Питання: де є недоліки
Intent: defects

Питання: проблемні проекти
Intent: defects

Питання: чи є косяки на об'єктах
Intent: defects

Питання: скільки активних проектів
Intent: projects

Питання: скільки клієнти винні
Intent: client_debt

Питання: заборгованість клієнтів
Intent: client_debt

Питання: хто ще не заплатив
Intent: client_debt

Питання: відкриті рекламації
Intent: reclamations

Питання: сервісні заявки
Intent: services

Можливі intent: stock_shortage, stock_balance, wallet_balance, expenses, salary, salary_total, salary_pending_total, salary_by_role, salary_pending_by_role, unpaid_workers, defects, projects, client_debt, equipment_gap, reclamations, services, unknown

Поверни тільки одне слово — intent.

Питання: {question}
Intent:
PROMPT;

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Detect intent for a question.
     *
     * @return string|null  Intent name, or null if cannot determine.
     */
    public function detectIntent(string $question): ?string
    {
        $started    = microtime(true);
        $normalized = $this->normalize($question);

        // 1. AI detection (local Ollama, strict 2s timeout)
        $intent = $this->detectViaAI($normalized);
        $source = 'ai';

        if (!$intent || $intent === 'unknown') {
            // 2. Keyword fallback (instant)
            $intent = $this->detectViaKeywords($normalized);
            $source = 'keyword';
        }

        $ms = (int) round((microtime(true) - $started) * 1000);

        Log::debug('IntentService', [
            'q'      => mb_substr($question, 0, 100),
            'intent' => $intent ?? 'null',
            'source' => $source,
            'ms'     => $ms,
        ]);

        return $intent;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Normalize question: lowercase + replace slang/synonyms.
     */
    private function normalize(string $question): string
    {
        $lq = mb_strtolower(trim($question));

        foreach (self::SYNONYM_MAP as $slang => $canonical) {
            $lq = str_replace($slang, $canonical, $lq);
        }

        return $lq;
    }

    private function detectViaAI(string $normalizedQuestion): ?string
    {
        $ollamaUrl = rtrim((string) config('services.ollama.url', 'http://localhost:11434'), '/');
        $model     = config('services.ollama.model', 'mistral');

        $prompt = str_replace('{question}', $normalizedQuestion, self::PROMPT_TEMPLATE);

        try {
            $response = Http::timeout(2)->post("{$ollamaUrl}/api/generate", [
                'model'  => $model,
                'prompt' => $prompt,
                'stream' => false,
            ]);

            if (!$response->successful()) return null;

            $text = trim(mb_strtolower((string) ($response->json('response') ?? '')));

            // Extract first word (model should return just the intent)
            $firstWord = explode("\n", $text)[0];
            $firstWord = trim(explode(' ', $firstWord)[0]);

            // Match against known intents
            foreach (self::INTENTS as $intent) {
                if ($firstWord === $intent || str_contains($text, $intent)) {
                    return $intent;
                }
            }

            if (str_contains($text, 'unknown')) return 'unknown';

        } catch (\Throwable $e) {
            Log::debug('IntentService: AI timeout/error', ['err' => $e->getMessage()]);
        }

        return null;
    }

    private function detectViaKeywords(string $normalizedQuestion): ?string
    {
        foreach (self::KEYWORD_MAP as $intent => $keywords) {
            foreach ($keywords as $kw) {
                if (str_contains($normalizedQuestion, $kw)) {
                    return $intent;
                }
            }
        }

        return null;
    }
}
