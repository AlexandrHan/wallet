<?php

namespace App\Services;

/**
 * Normalizes raw battery (АКБ) and BMS names to canonical format.
 *
 * Canonical battery format: {Brand} {ModelCode}
 *   SolaX T-BAT LV D53
 *   SolaX T-BAT SYS HV S3.6
 *   SolaX T-BAT SYS LV R36
 *   SolaX T-BAT H 3.0
 *   SolaX T-BAT H 5.8
 *   SolaX T-HS5.1
 *   SolaX TP-HS50E
 *   SolaX T30
 *   Deye BOS-G
 *   Deye BOS-G Pro
 *   Deye BOS-B
 *   Deye SE-G5.1 Pro-B
 *   Pylontech US5000
 *   Atlas В3
 *
 * Canonical BMS format:
 *   Built-in BMS
 *   SolaX TBMS MCS0800
 *   SolaX TBMS S51-8
 *   Deye BOS-B PDU 2A
 */
class BatteryNormalizerService
{
    // Values that mean "no battery" → return null
    private const NULL_BATTERY = [
        '-', '--', '---', '0', 'без', 'відсутні', 'відсутня', 'нет', 'ні', 'Ні', 'немає', 'no',
        'вже є', 'відсутнє', 'н/д',
    ];

    // Values that mean "no BMS" → return null
    private const NULL_BMS = [
        '-', '--', '---', '0', '000', 'відсутня', 'відсутній', 'відсутнє', 'немає', 'ні', 'нет', 'н/д',
    ];

    // ── Public API ─────────────────────────────────────────────────────────────

    public static function normalizeBattery(string $raw): ?string
    {
        $s = trim($raw);
        if ($s === '') return null;

        // Null patterns
        if (in_array(mb_strtolower($s), self::NULL_BATTERY)) return null;

        // "Trina Solar" is a panel, not a battery
        if (preg_match('/trina\s*solar/iu', $s)) return null;

        // Total-capacity-only values with no model info → ambiguous → null
        if (preg_match('/^\d+[,.]?\d*\s*(?:квт|kWh|kW)\s*$/iu', $s)) return null;

        // Multi-battery compound entries (contain comma with two distinct models): take first part
        if (preg_match('/,\s*(?:BOS|T-BAT|T BAT|ТВАТ|Deye|AKB)/iu', $s)) {
            $first = preg_split('/,\s*/', $s, 2)[0] ?? $s;
            $s = trim($first);
        }

        // ── Strip leading quantity noise ────────────────────────────────────────
        // "0шт PROSOLAX..." → strip "0шт"
        $s = preg_replace('/^0\s*шт\.?\s*/iu', '', $s);
        // "2шт - ", "1шт- ", "4шт.  ", "3 штуки "
        $s = preg_replace('/^\d+\s*(?:шт\.?|штуки?|АКБ)\s*[-–]\s*/iu', '', $s);
        $s = preg_replace('/^\d+\s*(?:шт\.?|штуки?)\s+/iu', '', $s);
        // "2 АКБ по" prefix
        $s = preg_replace('/^\d+\s*АКБ\s+(?:по\s*)?\d*/iu', '', $s);
        // Leading "АКБ "
        $s = preg_replace('/^(?:АКБ|акб)\s+/iu', '', $s);

        // ── Strip trailing quantity noise ───────────────────────────────────────
        // "- 2шт", ", 2 шт.", "/ 2шт", "-2шт.", "/2шт", " 2шт", "(16 шт) - 80 кВт"
        $s = preg_replace('/\s*[\(\[].*?[\)\]]\s*$/u', '', $s);     // remove parentheses
        $s = preg_replace('/\s*[-–,;\/]\s*\d+\s*(?:шт\.?|штуки?|АКБ)?\s*$/iu', '', $s);
        $s = preg_replace('/\s*\/\s*\d+\s*(?:шт\.?)?\s*$/iu', '', $s);
        $s = preg_replace('/[-–]\s*\d+\s*(?:квт.*)?$/iu', '', $s);   // "- 80 кВт"
        $s = preg_replace('/\s+\d+\s*(?:шт\.?|штуки?)?\s*$/iu', '', $s);
        $s = preg_replace('/\s*б\/у[?]?\s*$/iu', '', $s);            // "б/у?"
        $s = trim($s, " \t\r\n-–,./");
        $s = trim($s);

        if ($s === '') return null;

        // ── Detect brand ────────────────────────────────────────────────────────

        $isSolax = preg_match('/(?:prosolax|prosolaxt|prosolxt|solax|солакс|powerт|т-бат|тват|т\s*ват)/iu', $s)
            || preg_match('/\b(?:T-BAT|TBAT|T BAT|T-HS|T HS|TP-HS|TP HS|TB-HS|TB HS|TB HR|T30|T-LV)/i', $s)
            || preg_match('/\b(?:D53|S3[,.]6|R36|HS5[,.]1|HS50E|MCS0800|HV10230)/i', $s);

        $isDeye  = preg_match('/\bdeye\b|\bBOS-[GB]\b|\bBOS [GB]\b|\bBOS_[GB]\b|\bSE-G5/iu', $s)
            || preg_match('/\bBOS-B-Pack|\bBOS B PDU/iu', $s);

        $isPylontech = preg_match('/pylontech|pilontech/iu', $s);
        $isAtlas     = preg_match('/\batlas\b/iu', $s);

        if ($isSolax) {
            // Strip brand prefix before model parsing
            $model = $s;
            $model = preg_replace('/^(?:prosolax|prosolaxt|prosolxt)\s*/iu', '', $model);
            $model = preg_replace('/^(?:solax\s+power|solax|powerт?)\s*/iu', '', $model);
            $model = preg_replace('/^(?:солакс|тват|т-бат|т\s*ват)\s*/iu', '', $model);
            $model = trim($model, " \t-–");
            return self::normalizeSolaxModel($model, $s);
        }

        if ($isDeye) {
            return self::normalizeDeyeBattery($s);
        }

        if ($isPylontech) {
            return 'Pylontech US5000';
        }

        if ($isAtlas) {
            return 'Atlas В3';
        }

        // ── Partial code detection (no recognizable brand prefix) ──────────────
        if (preg_match('/\bD53\b|LVD53|LD53/iu', $s)) return 'SolaX T-BAT LV D53';
        if (preg_match('/S3[,.]6\b|HVS3|SYS\s*HV\s*S/iu', $s)) return 'SolaX T-BAT SYS HV S3.6';
        if (preg_match('/R36\b|SYSLV\s*R|SYS\s*LV\s*R/iu', $s)) return 'SolaX T-BAT SYS LV R36';
        if (preg_match('/\bHS5[,.]1\b|T-HS5|THS51/iu', $s)) return 'SolaX T-HS5.1';
        if (preg_match('/HS50E\b/iu', $s)) return 'SolaX TP-HS50E';
        if (preg_match('/HV10230/iu', $s)) return 'SolaX T-BAT H 3.0';

        // Cyrillic T-BAT variants: Т ВАТ, ТВАТ, т-бат
        if (preg_match('/(?:Т[-\s]?ВАТ|ТВАТ)\s*(?:LV\s*D53|ЛВ\s*D53|D53)/iu', $s)) return 'SolaX T-BAT LV D53';
        if (preg_match('/(?:Т[-\s]?ВАТ|ТВАТ)\s*3[.,]6/iu', $s)) return 'SolaX T-BAT SYS HV S3.6';
        if (preg_match('/(?:Т[-\s]?ВАТ|ТВАТ)\s*3[.,]0/iu', $s)) return 'SolaX T-BAT H 3.0';

        // kWh-only with LV context → D53
        if (preg_match('/(?:LV|ЛВ|лв)\s*5[.,]3|5[.,]3\s*(?:LV|ЛВ|лв)/iu', $s)) return 'SolaX T-BAT LV D53';

        // Ambiguous pure numbers — null
        return null;
    }

    public static function normalizeBms(string $raw): ?string
    {
        $s = trim($raw);
        if ($s === '') return null;

        if (in_array(mb_strtolower($s), self::NULL_BMS)) return null;

        // Built-in
        if (preg_match('/вбудован|встроен|built.in|built\s*in/iu', $s)) return 'Built-in BMS';

        // SolaX TBMS MCS0800 (for T-BAT LV D53)
        if (preg_match('/TBMS[-\s]*MCS0800/iu', $s)) return 'SolaX TBMS MCS0800';

        // SolaX TBMS S51-8 (for T-HS5.1)
        if (preg_match('/TBMS[-\s]*S51[-\s]*8/iu', $s)) return 'SolaX TBMS S51-8';

        // Deye BOS-B PDU
        if (preg_match('/BOS[-\s]?B[-\s]?PDU/iu', $s)) {
            if (preg_match('/BOS[-\s]?B[-\s]?PDU[-\s]?(\d+)[-\s]?([A-Z])/iu', $s, $m)) {
                return 'Deye BOS-B PDU ' . $m[1] . $m[2];
            }
            return 'Deye BOS-B PDU 2A';
        }

        return null;
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    private static function normalizeSolaxModel(string $model, string $original): string
    {
        // Normalize internal spacing/dashes for matching
        $n = preg_replace('/\s+/', ' ', $model);

        // T-HS5.1 / T HS5.1 (HV 5.1kWh)
        if (preg_match('/T[-\s]?HS\s*5[,.]1|THS5[,.]1|HS51/iu', $n)) return 'SolaX T-HS5.1';

        // TP-HS50E (5.0kWh)
        if (preg_match('/TP[-\s]?HS\s*50E|HS50E/iu', $n)) return 'SolaX TP-HS50E';

        // TB HR140 (14kWh pack)
        if (preg_match('/TB[-\s]?HR\s*140|HR140/iu', $n)) return 'SolaX TB HR140';

        // T-BAT H 5.8 (HV 5.8kWh Master/Slave)
        if (preg_match('/(?:T[-\s]?BAT\s*)?H\s*5[,.]8|5[,.]8/iu', $n)) return 'SolaX T-BAT H 5.8';

        // T-BAT H 3.0 / HV10230 (HV 3.0kWh)
        if (preg_match('/(?:T[-\s]?BAT\s*)?H\s*3[,.]0|HV10230|3[,.]0/iu', $n)) return 'SolaX T-BAT H 3.0';

        // T30 / Solax T30 (3.1kWh cylindrical)
        if (preg_match('/\bT30\b|\bT\s*30\b|3[,.]1/iu', $n)) return 'SolaX T30';

        // T-BAT LV D53 (LV 5.3kWh) — check BEFORE generic 5.3
        if (preg_match('/D53|LVD53|LD53/iu', $n)) return 'SolaX T-BAT LV D53';
        if (preg_match('/T[-\s]?BAT[-\s]?LV\b|TBAT\s*LV|BAT\s*LV\b/iu', $n)) return 'SolaX T-BAT LV D53';
        // "LV" alone refers to D53 in SolaX context
        if (preg_match('/^LV\b|[-\s]LV\b|LV[-\s]?D|\bLD\b/iu', $n)) return 'SolaX T-BAT LV D53';

        // T-BAT SYS LV R36 (LV 3.6kWh) — check BEFORE HV S3.6
        if (preg_match('/R36|SYS[-\s]?LV|SYSLV|BAT[-\s]?SYS[-\s]?LV|T[-\s]?BAT[-\s]?SYSLV/iu', $n)) {
            return 'SolaX T-BAT SYS LV R36';
        }

        // T-BAT SYS HV S3.6 (HV 3.6kWh)
        if (preg_match('/S3[,.]6|HV\s*S3|SYS[-\s]?HV|SYSHV|3[,.]6/iu', $n)) {
            return 'SolaX T-BAT SYS HV S3.6';
        }

        // LV 5.3 / 5.3 LV context → D53
        if (preg_match('/(?:LV|ЛВ|лв)\s*5[,.]3|5[,.]3\s*(?:LV|ЛВ|лв)/iu', $original)) {
            return 'SolaX T-BAT LV D53';
        }

        // Plain 5.3 in SolaX context → assume D53 (most common 5.3kWh model)
        if (preg_match('/5[,.]3/iu', $n)) return 'SolaX T-BAT LV D53';

        // "3.6" in SolaX context without LV/R36 → HV S3.6 (most common)
        if (preg_match('/3[,.]6/iu', $n)) return 'SolaX T-BAT SYS HV S3.6';

        return 'SolaX';
    }

    private static function normalizeDeyeBattery(string $s): string
    {
        if (preg_match('/SE[-\s]?G5[,.]1/iu', $s)) return 'Deye SE-G5.1 Pro-B';
        if (preg_match('/BOS[-_\s]?G[-_\s]?PRO|BOS[-_\s]?G[-_\s]?PACK/iu', $s)) return 'Deye BOS-G Pro';
        if (preg_match('/BOS[-_\s]?B[-_\s]?Pack|BOS[-_\s]?B\b/iu', $s)) return 'Deye BOS-B';
        if (preg_match('/BOS[-_\s]?G\b|BOS\s+5[,.]1/iu', $s)) return 'Deye BOS-G';
        return 'Deye';
    }
}
