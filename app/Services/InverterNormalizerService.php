<?php

namespace App\Services;

/**
 * Normalizes raw inverter name strings to a canonical format.
 *
 * Canonical Solax format: Solax {X1|X3} {Hybrid|Grid} {Power}K [{LV|HV}]
 * Canonical non-Solax:    {Brand} {model/power}
 */
class InverterNormalizerService
{
    /**
     * Normalize a raw inverter string.
     * Returns null when the value is not a recognizable inverter.
     */
    public static function normalize(string $raw): ?string
    {
        $s = trim($raw);

        if ($s === '' || in_array(mb_strtolower($s), ['-', '—', 'ні', 'вже є', 'н/д'])) {
            return null;
        }

        // Strip leading quantity/role noise: "1шт ", "2інв ", "3 штуки ", "його ", "інвертор "
        $s = preg_replace('/^\d+\s*(?:шт\.?|інв\.?|штуки?)\s+/iu', '', $s);
        $s = preg_replace('/^(?:його|її|їх|інвертор|інвектор)\s+/iu', '', $s);
        $s = trim($s);

        // Strip trailing noise: "- 2 шт", "б/у", ", Mate box..."
        $s = preg_replace('/\s*[-–,]\s*\d+\s*шт\.?\s*$/iu', '', $s);
        $s = preg_replace('/\s*,\s*(?:mate\s*box|wifi|wi-fi|bms|стойк|стійк).*/iu', '', $s);
        $s = preg_replace('/\s+б\/у\s*$/iu', '', $s);
        $s = trim($s);
        // Normalize decimal commas "7,5" → "7.5" to avoid spurious comma splits
        $s = (string) preg_replace('/(\d),(\d)/', '$1.$2', $s);
        // Normalize "12 .0" → "12.0" (space before decimal point)
        $s = (string) preg_replace('/(\d)\s+\.(\d)/', '$1.$2', $s);

        // Multi-inverter: split on "+" or comma followed by a brand/type keyword
        if (str_contains($s, '+') ||
            preg_match('/,\s*(?:solax|prosolax|deye|solis|huawei|солакс|солак|мережевий|гібрид)/iu', $s)
        ) {
            $parts = str_contains($s, '+')
                ? preg_split('/\s*\+\s*/', $s, -1, PREG_SPLIT_NO_EMPTY)
                : preg_split('/,\s*/', $s, -1, PREG_SPLIT_NO_EMPTY);

            if (count($parts) > 1) {
                $normalized = array_values(array_filter(
                    array_map(fn($p) => self::normalize(trim($p)), $parts)
                ));
                if ($normalized) {
                    return implode(' + ', $normalized);
                }
            }
        }

        // ── Non-Solax brand detection ──────────────────────────────────────────

        // SUN-XXK without Solax context = Deye
        if (!preg_match('/solax|prosolax/iu', $s) && preg_match('/\bSUN[-\s]\d+K/i', $s)) {
            return self::normalizeDeye($s);
        }
        // S5-GC/GR prefix = Solis
        if (preg_match('/\bS5-G[CR]/i', $s) && !preg_match('/solax/iu', $s)) {
            return self::normalizeSolis($s);
        }

        if (preg_match('/\bdeye\b|(?<!\w)дея(?!\w)/iu', $s)) {
            return self::normalizeDeye($s);
        }
        if (preg_match('/\bsolis\b|(?<!\w)соліс(?!\w)/iu', $s)) {
            return self::normalizeSolis($s);
        }
        if (preg_match('/\bhuawei\b|(?<!\w)хуавей(?!\w)/iu', $s)) {
            return self::normalizeHuawei($s);
        }
        if (preg_match('/\bvictron\b|(?<!\w)віктрон(?!\w)/iu', $s)) {
            return self::normalizeVictron($s);
        }
        if (preg_match('/\bsolaredge\b/iu', $s)) {
            $kw = self::kwStr($s);
            return 'SolarEdge' . ($kw ? ' ' . $kw : '');
        }
        if (preg_match('/\batlas\b/iu', $s)) {
            $kw = self::kwStr($s);
            return 'Atlas' . ($kw ? ' ' . $kw : '');
        }
        if (preg_match('/(?<![a-z])ies(?![a-z])/iu', $s)) {
            if (preg_match('/ies\s*(\d+)/iu', $s, $m)) {
                return 'IES ' . $m[1] . 'K';
            }
        }

        // ── Solax detection ────────────────────────────────────────────────────
        $isSolax = preg_match('/solax|prosolax/iu', $s)            // "Solax", "SolaX", "Solax6LV"
            || preg_match('/(?<!\w)(?:солакс|солак)(?!\w)/iu', $s)
            || preg_match('/(?:aelio|аліо|alio)/iu', $s)
            || preg_match('/[Xx\x{0425}\x{0445}]\s*[13][-\s]/u', $s)  // "X1-", "X3-", "X 3 "
            || preg_match('/\b[Xx][13]\b/u', $s)                       // standalone X1 X3
            || preg_match('/[Xx][13](?:HYB|NEO|LITE|BOOST|ULT|FTH|MGA|AELIO|HYBRID|PRO)/i', $s)
            || preg_match('/\d+ultra/iu', $s)                          // "30Ultra"
            || preg_match('/\bult\b/iu', $s);                          // "X3-ULT-30K"

        if ($isSolax) {
            return self::normalizeSolax($s);
        }

        return $s;
    }

    // ── Power extraction ──────────────────────────────────────────────────────

    private static function kwValue(string $s): ?float
    {
        // Run-together power+voltage: "20KLV", "15KHV"
        if (preg_match('/\b(\d+)K(?:LV|HV)\b/i', $s, $m) && (int)$m[1] >= 1 && (int)$m[1] <= 500) {
            return (float)(int)$m[1];
        }
        // Run-together power+other-letter: "50KG2", "AELIO50K" (K followed by uppercase non-voltage)
        if (preg_match('/\b(\d+)K[A-Z]/i', $s, $m) && (int)$m[1] >= 1 && (int)$m[1] <= 500) {
            return (float)(int)$m[1];
        }
        // Digit stuck directly after letters: "AELIO50K", "X3AELIO50K"
        if (preg_match('/(?<=[A-Za-z])(\d+)K/i', $s, $m) && (int)$m[1] >= 1 && (int)$m[1] <= 500) {
            return (float)(int)$m[1];
        }
        // Decimal: "1.1", "6.0", "7.5", "15.0M" — allow optional trailing letter
        if (preg_match('/\b(\d+)[.,](\d+)[A-Za-z]?/u', $s, $m)) {
            $v = (float)($m[1] . '.' . $m[2]);
            if ($v >= 0.5 && $v <= 500) return $v;
        }
        // Compact brand+number: "Solax6LV" (no space)
        if (preg_match('/(?:solax|prosolax)(\d+)/iu', $s, $m) && (int)$m[1] >= 1 && (int)$m[1] <= 500) {
            return (float)(int)$m[1];
        }
        // Brand with space: "Solax 125", "Solax 12 X3", "Prosolax 15"
        if (preg_match('/(?:solax|prosolax)\s+(\d+)/iu', $s, $m) && (int)$m[1] >= 1 && (int)$m[1] <= 500) {
            return (float)(int)$m[1];
        }
        // Standard: "125K", "30KW", "6k"
        if (preg_match('/\b(\d+)\s*k(?:w|вт)?(?=[^a-zA-Zа-яіїєґ]|$)/iu', $s, $m) && (int)$m[1] >= 1 && (int)$m[1] <= 500) {
            return (float)(int)$m[1];
        }
        // "30 кВт", "6квт"
        if (preg_match('/\b(\d+)\s*квт?\b/iu', $s, $m) && (int)$m[1] >= 1 && (int)$m[1] <= 500) {
            return (float)(int)$m[1];
        }
        // "30к" — lone Ukrainian к shorthand
        if (preg_match('/\b(\d+)\s*к(?!\w)/iu', $s, $m) && (int)$m[1] >= 1 && (int)$m[1] <= 500) {
            return (float)(int)$m[1];
        }
        // "15 LV", "30 HV"
        if (preg_match('/\b(\d+)\s+(?:lv|hv|лв|нв)\b/iu', $s, $m) && (int)$m[1] >= 1 && (int)$m[1] <= 500) {
            return (float)(int)$m[1];
        }
        // "LV 15", "лв 6"
        if (preg_match('/(?:lv|hv|лв|нв)\s+(\d+)/iu', $s, $m) && (int)$m[1] >= 1 && (int)$m[1] <= 500) {
            return (float)(int)$m[1];
        }
        // After brand keyword (with optional space): "Аліо 50", "Solax 30", "Deye 50"
        if (preg_match('/(?:солакс?|солак|аліо|aelio|alio|deye|solis|huawei|дея|ies|atlas)\s*(\d+)/iu', $s, $m) && (int)$m[1] >= 1 && (int)$m[1] <= 500) {
            return (float)(int)$m[1];
        }
        // "30Ultra"
        if (preg_match('/(\d+)\s*ultra/iu', $s, $m) && (int)$m[1] >= 1 && (int)$m[1] <= 500) {
            return (float)(int)$m[1];
        }
        // Fallback: trailing standalone number e.g. "Солакс Гібрид 15", "Solax 12 X3 something"
        if (preg_match('/\b(\d+)\s*$/u', $s, $m) && (int)$m[1] >= 1 && (int)$m[1] <= 500) {
            return (float)(int)$m[1];
        }
        return null;
    }

    private static function kwStr(string $s): string
    {
        $v = self::kwValue($s);
        if ($v === null) return '';
        return ($v == (int)$v) ? ((int)$v . 'K') : ($v . 'K');
    }

    // ── Brand-specific normalizers ─────────────────────────────────────────────

    private static function normalizeDeye(string $s): string
    {
        if (preg_match('/SUN[-\s](\d+)K/i', $s, $m)) {
            return 'Deye SUN-' . $m[1] . 'K';
        }
        $kw = self::kwStr($s);
        return 'Deye' . ($kw ? ' ' . $kw : '');
    }

    private static function normalizeSolis(string $s): string
    {
        if (preg_match('/S5-G[CR]\w*/i', $s, $m)) {
            return 'Solis ' . strtoupper($m[0]);
        }
        $kw = self::kwStr($s);
        return 'Solis' . ($kw ? ' ' . $kw : '');
    }

    private static function normalizeHuawei(string $s): string
    {
        $kw = self::kwStr($s);
        return 'Huawei' . ($kw ? ' ' . $kw : '');
    }

    private static function normalizeVictron(string $s): string
    {
        if (preg_match('/(\d+)\s*k?va/iu', $s, $m)) {
            return 'Victron ' . $m[1] . 'KVA';
        }
        $kw = self::kwStr($s);
        return 'Victron' . ($kw ? ' ' . $kw : '');
    }

    private static function normalizeSolax(string $s): string
    {
        // ── Strip generation/revision noise before parsing ──────────────────────
        // Remove: "-G2", "-G4", "G2", "G4", "-P" (trailing revision markers)
        $clean = (string) preg_replace('/[-\s]G[24]\b/i', '', $s);
        $clean = (string) preg_replace('/-P\b/i', '', $clean);
        // Strip "Power" when part of brand "SolaX Power"
        $clean = (string) preg_replace('/\bpower\b/iu', '', $clean);
        // Strip marketing noise
        $clean = (string) preg_replace(
            '/\b(?:під\s+власне\s+споживання|новинка|очікується|під\s+замовлення)\b/iu', '', $clean
        );
        $clean = trim($clean);

        // ── Phase (X1 / X3) ────────────────────────────────────────────────────
        $phase = null;
        if (preg_match('/[Xx\x{0425}\x{0445}]\s*([13])\b/u', $clean, $m)) {
            $phase = 'X' . $m[1];
        } elseif (preg_match('/трифазн|3\s*(?:ф|фаз)|3ф\b/iu', $clean)) {
            $phase = 'X3';
        } elseif (preg_match('/однофаз|1\s*(?:ф|фаз)|1ф\b/iu', $clean)) {
            $phase = 'X1';
        } elseif (preg_match('/(?:aelio|аліо|alio)/iu', $clean)) {
            $phase = 'X3'; // Aelio is always X3
        }

        // ── Ultra? ─────────────────────────────────────────────────────────────
        $isUltra = (bool) preg_match('/\d+ultra|\bult(?:ra)?\b/iu', $clean);

        // ── Voltage flags (needed before sub-model/type resolution) ───────────────
        $hasLVEarly = (bool) preg_match('/\bLV\b|низьковольтн|\bлв\b/iu', $clean)
                   || (bool) preg_match('/\d+K?LV\b/i', $clean);
        $hasHVEarly = (bool) preg_match('/\bHV\b|\bнв\b/iu', $clean)
                   || (bool) preg_match('/\d+K?HV\b/i', $clean);

        // ── Grid sub-models (always Grid, regardless of LV/HV) ─────────────────
        // LITE without LV/HV is also grid (X3-LITE is a grid-tied series)
        $gridSubmodel = null;
        if      (preg_match('/\bS-D\b/i',    $clean)) $gridSubmodel = 'S-D';
        elseif  (preg_match('/\bBOOST\b/iu', $clean)) $gridSubmodel = 'Boost';
        elseif  (preg_match('/\bPRO\b/iu',   $clean)) $gridSubmodel = 'Pro';
        elseif  (preg_match('/\bMGA\b/iu',   $clean)) $gridSubmodel = 'MGA';
        elseif  (preg_match('/\bFTH\b/iu',   $clean)) $gridSubmodel = 'FTH';
        elseif  (preg_match('/\bGRD\b/iu',   $clean)) $gridSubmodel = 'GRD';
        elseif  (preg_match('/\bLITE\b/iu',  $clean) && !$hasLVEarly) $gridSubmodel = 'Lite';

        // ── Hybrid sub-models ──────────────────────────────────────────────────
        $hybridSubmodel = null;
        if (!$gridSubmodel && !$isUltra) {
            if (preg_match('/(?:aelio|аліо|alio)/iu', $clean)) $hybridSubmodel = 'Aelio';
            // NEO and Lite are stripped — both normalize to plain "Hybrid"
        }

        // ── Voltage flags (aliases for readability below) ──────────────────────
        $hasLV = $hasLVEarly;
        $hasHV = $hasHVEarly;

        // ── Type ───────────────────────────────────────────────────────────────
        // Priority: explicit grid sub-model > explicit hybrid keywords > LV/HV > ambiguous
        $type = null;
        if ($gridSubmodel) {
            $type = 'Grid';
        } elseif (preg_match('/(?:aelio|аліо|alio)/iu', $clean)) {
            $type = 'Hybrid';
        } elseif ($isUltra) {
            $type = 'Hybrid'; // X3-ULT = Hybrid Ultra
        } elseif (preg_match('/\bNEO\b/iu', $clean)) {
            $type = 'Hybrid'; // NEO = Hybrid
        } elseif (preg_match('/\b(?:hyb(?:rid)?)\b|гібрид(?:ний)?/iu', $clean)) {
            $type = 'Hybrid';
        } elseif ($hasLV) {
            $type = 'Hybrid'; // LV = low-voltage battery port = Hybrid
        } elseif ($hasHV) {
            $type = 'Hybrid'; // HV without known grid sub-model = Hybrid
        } elseif (preg_match('/\b(?:lite|life|grid)\b|мережевий|мережний|мережа/iu', $clean)) {
            $type = 'Grid';
        }

        // ── Power ──────────────────────────────────────────────────────────────
        $stripped = (string) preg_replace('/\s+(?:і|й)\s+\d+.{0,30}$/u', '', $clean);
        $kw = self::kwValue($stripped);
        $powerStr = null;
        if ($kw !== null) {
            $powerStr = ($kw == (int)$kw) ? ((int)$kw . 'K') : ($kw . 'K');
        }

        // ── Voltage suffix ─────────────────────────────────────────────────────
        // Grid: only HV for GRD model. All other grid sub-models: no voltage suffix.
        // Hybrid: LV or HV as battery spec.
        $voltageSuffix = null;
        if ($type === 'Grid') {
            if ($hasHV && $gridSubmodel === 'GRD') {
                $voltageSuffix = 'HV';
            }
        } else {
            if ($hasLV)       $voltageSuffix = 'LV';
            elseif ($hasHV)   $voltageSuffix = 'HV';
        }

        // ── Assemble ───────────────────────────────────────────────────────────
        $parts = ['Solax'];
        if ($phase) $parts[] = $phase;
        if ($type)  $parts[] = $isUltra ? $type . ' Ultra' : $type;

        if ($type === 'Grid') {
            // Canonical: Solax {X1|X3} Grid {Power}K {Series} {HV?}
            if ($powerStr)      $parts[] = $powerStr;
            if ($gridSubmodel)  $parts[] = $gridSubmodel;
            if ($voltageSuffix) $parts[] = $voltageSuffix;
        } else {
            // Canonical: Solax {X1|X3} Hybrid {SubModel?} {Power}K {LV|HV?}
            if ($hybridSubmodel) $parts[] = $hybridSubmodel;
            if ($powerStr)       $parts[] = $powerStr;
            if ($voltageSuffix)  $parts[] = $voltageSuffix;
        }

        return (count($parts) > 1) ? implode(' ', $parts) : 'Solax';
    }
}
