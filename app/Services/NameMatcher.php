<?php

namespace App\Services;

/**
 * Unified name normalization and fuzzy matching.
 *
 * Single source of truth used by all automation sync routes.
 * Algorithm is intentionally identical to the original inline closures
 * in routes/api.php — do not change without updating both sides.
 */
class NameMatcher
{
    /**
     * Normalize a name for comparison:
     *   - lowercase
     *   - strip everything except Unicode letters and digits
     *   - collapse whitespace
     */
    public static function normalize(string $value): string
    {
        $value = mb_strtolower($value);
        $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? '';
        $value = preg_replace('/\s+/u', ' ', $value) ?? '';
        return trim($value);
    }

    /**
     * Compact form: normalized value with all spaces removed.
     * Used for substring matching to handle "ПрізвищеІм'я" vs "Прізвище Ім'я".
     */
    public static function compact(string $value): string
    {
        return str_replace(' ', '', self::normalize($value));
    }

    /**
     * Fuzzy match score between needle (name from sheet) and haystack (client_name in DB).
     * Returns 0.0–100.0. Threshold for a valid match is 72.0.
     *
     * Scoring ladder:
     *   100 — exact substring after normalize
     *    96 — exact substring after compact (handles missing spaces)
     *   0–100 — best of: similar_text on compact pair, or token-by-token on haystack words
     */
    public static function score(string $needle, string $haystack): float
    {
        $needleNorm    = self::normalize($needle);
        $haystackNorm  = self::normalize($haystack);

        if ($needleNorm === '' || $haystackNorm === '') {
            return 0.0;
        }

        if (str_contains($haystackNorm, $needleNorm)) {
            return 100.0;
        }

        $needleCompact   = self::compact($needleNorm);
        $haystackCompact = self::compact($haystackNorm);

        if ($needleCompact !== '' && $haystackCompact !== ''
            && str_contains($haystackCompact, $needleCompact)
        ) {
            return 96.0;
        }

        similar_text($needleCompact, $haystackCompact, $mainPercent);
        $best = (float) $mainPercent;

        foreach (preg_split('/\s+/u', $haystackNorm) ?: [] as $token) {
            $token = trim($token);
            if ($token === '') {
                continue;
            }

            similar_text($needleNorm, $token, $tokenPercent);
            if ((float) $tokenPercent > $best) {
                $best = (float) $tokenPercent;
            }

            $tokenCompact = self::compact($token);
            if ($tokenCompact !== '') {
                similar_text($needleCompact, $tokenCompact, $tokenCompactPercent);
                if ((float) $tokenCompactPercent > $best) {
                    $best = (float) $tokenCompactPercent;
                }
            }
        }

        return $best;
    }
}
