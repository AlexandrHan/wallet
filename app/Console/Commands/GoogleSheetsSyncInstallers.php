<?php

namespace App\Console\Commands;

use App\Services\GoogleSheetsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Sync installation team schedules from Google Sheets → sales_projects + project_schedule_entries.
 *
 * Sheet tab name = first word of installation_team (e.g. "Кукуяка").
 * Sheet columns A:G:
 *   A = day of week (ignored)
 *   B = date of that day
 *   C = client name
 *   D = settlement
 *   E = installation works description
 *   F = (unused — kept for column alignment)
 *   G = notes
 *
 * Multi-row logic:
 *   The same client can appear in multiple CONSECUTIVE rows (one per work day).
 *   We find the first matching row, then collect all consecutive rows that match
 *   the same client. Each row = one work day = one schedule entry.
 */
class GoogleSheetsSyncInstallers extends Command
{
    protected $signature = 'sheets:sync-installers';

    protected $description = 'Sync installer work dates from Google Sheets to sales_projects (multi-row, per-day)';

    // ── Ukrainian text normalization ─────────────────────────────────────────

    private function normalizeUkr(string $text): string
    {
        $text = mb_strtolower(trim($text));
        // Strip apostrophes and soft signs (straight, typographic, modifier letter)
        $text = str_replace(["'", "\u{2019}", "\u{02BC}", "\u{0060}", "\u{00B4}"], '', $text);
        // Ukrainian letter equivalences that vary between sources
        $text = str_replace(['ї', 'і', 'є'], ['и', 'и', 'е'], $text);
        // Gendered suffix variations
        $text = str_replace(['ська', 'зька', 'цька'], ['ска', 'зка', 'цка'], $text);
        $text = str_replace(['ський', 'зький', 'цький'], ['ский', 'зкий', 'цкий'], $text);
        return $text;
    }

    private function normalizeClientSurname(string $text): string
    {
        $parts = $this->normalizeClientTokens($text);
        $surname = $parts[0] ?? '';

        return $surname;
    }

    private function normalizeClientGivenName(string $text): string
    {
        $parts = $this->normalizeClientTokens($text);

        return $parts[1] ?? '';
    }

    /**
     * @return string[]
     */
    private function normalizeClientTokens(string $text): array
    {
        $text = preg_replace('/\(.*?\)/u', '', $text);
        $text = trim((string) $text);
        if ($text === '') return [];

        $parts = preg_split('/[\s,;\/]+/u', $text);
        $tokens = [];

        foreach ($parts as $part) {
            $part = trim((string) $part, '.,;:!?-');
            if ($part === '') continue;
            $tokens[] = $this->normalizeUkr($part);
        }

        return $tokens;
    }

    /**
     * Extract significant keywords from a client name for fuzzy matching.
     * Removes content in brackets, splits by whitespace/comma, keeps words > 3 chars.
     * Returns both original-lowercased and Ukrainian-normalized versions.
     *
     * @return array{keywords: string[], normalized: string[]}
     */
    private function extractKeywords(string $clientName): array
    {
        // Remove bracketed suffixes like "(Калинопіль)"
        $clean = preg_replace('/\(.*?\)/', '', $clientName);
        $clean = trim((string) $clean);

        $words = preg_split('/[\s,;\/]+/', $clean);
        $keywords   = [];
        $normalized = [];

        foreach ($words as $word) {
            $word = trim($word, '.,;:!?-');
            if (mb_strlen($word) < 3) continue;
            $keywords[]   = mb_strtolower($word);
            $normalized[] = $this->normalizeUkr($word);
        }

        return ['keywords' => array_unique($keywords), 'normalized' => array_unique($normalized)];
    }

    /**
     * Check whether a sheet column-C value matches a project client surname exactly.
     */
    private function colCMatchesClient(
        string $colC,
        string $clientNameNorm,
        string $projectGivenNameNorm,
        bool $requiresGivenName,
        array  $keywords,
        array  $normalizedKeywords
    ): bool {
        if ($colC === '') return false;

        $sheetTokens = $this->normalizeClientTokens($colC);
        if (empty($sheetTokens)) return false;

        if (($sheetTokens[0] ?? '') !== $clientNameNorm) {
            return false;
        }

        if (!$requiresGivenName) {
            return true;
        }

        $sheetGivenNameNorm = $sheetTokens[1] ?? '';
        if ($sheetGivenNameNorm === '' || $projectGivenNameNorm === '') {
            return false;
        }

        return $sheetGivenNameNorm === $projectGivenNameNorm;
    }

    // ── Date normalization ───────────────────────────────────────────────────

    private function normalizeDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') return null;

        // ISO format
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return $value;

        // D.M.YYYY or D/M/YYYY or D-M-YYYY (e.g. 5.1.2026)
        if (preg_match('/^(\d{1,2})[.\/-](\d{1,2})[.\/-](\d{2,4})$/', $value, $m)) {
            $d = (int) $m[1]; $mo = (int) $m[2]; $y = (int) $m[3];
            if ($y < 100) $y += 2000;
            if (checkdate($mo, $d, $y)) return sprintf('%04d-%02d-%02d', $y, $mo, $d);
        }

        // Excel serial number
        if (is_numeric($value) && (int) $value > 1000) {
            try {
                return \Carbon\Carbon::create(1899, 12, 30)->addDays((int) $value)->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        }

        // Carbon generic parse
        try {
            return \Carbon\Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    // ── Main ─────────────────────────────────────────────────────────────────

    public function handle(): int
    {
        $skipWords = ['вихідні', 'вихідний', 'сервіси', 'вихідний день'];

        // ── All active projects (including those with NULL/empty installation_team) ─
        $projects = DB::table('sales_projects')
            ->select([
                'id', 'client_name', 'installation_team',
                'panel_work_start_date', 'panel_work_days',
                'installation_team_note', 'installation_team_task_note',
            ])
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('construction_status')
                  ->orWhereNotIn('construction_status', ['salary_paid', 'salary_pending']);
            })
            ->orderBy('id')
            ->get();

        if ($projects->isEmpty()) {
            $this->info('No active projects — nothing to sync.');
            return self::SUCCESS;
        }

        // ── Determine all tab names to scan ──────────────────────────────────
        // Collect from ALL projects in DB, splitting comma-joined multi-team strings.
        $allTabNames = DB::table('sales_projects')
            ->whereNotNull('installation_team')
            ->where('installation_team', '!=', '')
            ->where('installation_team', '!=', 'Без монтажних робіт')
            ->pluck('installation_team')
            ->flatMap(fn ($t) => array_map('trim', explode(',', $t)))
            ->filter(fn ($t) => $t !== '' && $t !== 'Без монтажних робіт')
            ->map(fn ($t) => explode(' ', trim($t))[0])
            ->filter(fn ($t) => $t !== '')
            ->unique()
            ->values()
            ->all();

        try {
            $sheets = new GoogleSheetsService();
        } catch (\Throwable $e) {
            Log::error('sheets:sync-installers: GoogleSheetsService init failed', ['err' => $e->getMessage()]);
            $this->error('GoogleSheetsService init failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        // ── Load all sheet tabs upfront ───────────────────────────────────────
        $sheetCache    = []; // tabName → raw rows[]
        $missingSheets = [];

        foreach ($allTabNames as $tabName) {
            try {
                $sheetCache[$tabName] = $sheets->getSheetRows($tabName, 'A:G');
            } catch (\Throwable $e) {
                $missingSheets[] = $tabName;
                Log::warning('sheets:sync-installers: sheet not found',
                    ['sheet' => $tabName, 'err' => $e->getMessage()]);
                $this->warn("Вкладка '{$tabName}' не знайдена: " . $e->getMessage());
            }
        }

        if (empty($sheetCache)) {
            $this->error('No sheet tabs loaded — aborting.');
            return self::FAILURE;
        }

        // ── Strip header row from each tab ────────────────────────────────────
        $dataRowsPerTab = []; // tabName → data rows[]
        foreach ($sheetCache as $tabName => $allRows) {
            if (empty($allRows)) { $dataRowsPerTab[$tabName] = []; continue; }
            $startIndex = 0;
            $firstCell  = trim((string) ($allRows[0][0] ?? ''));
            if ($firstCell !== '' && !is_numeric($firstCell)
                && $this->normalizeDate($firstCell) === null
                && mb_strlen($firstCell) < 20) {
                $startIndex = 1;
            }
            $dataRowsPerTab[$tabName] = array_slice($allRows, $startIndex);
        }

        // ── Pre-compute duplicate groups per tab ──────────────────────────────
        // duplicateGroupsPerTab[tabName][clientNorm+'|'+tabName] → [project_ids]
        // Used for settlement disambiguation when same client appears twice in one tab.
        $duplicateGroupsPerTab = [];
        foreach (array_keys($sheetCache) as $tabName) {
            $duplicateGroupsPerTab[$tabName] = [];
            foreach ($projects as $p) {
                $pName = trim((string) $p->client_name);
                if (preg_match('/^(.+),\s+\S+(?:\s+\S+){0,2}\s*$/su', $pName, $dsm)) {
                    $pName = trim($dsm[1]);
                }
                $dKey = mb_strtolower($pName) . '|' . $tabName;
                $duplicateGroupsPerTab[$tabName][$dKey][] = $p->id;
            }
        }

        // ── Pre-compute ambiguous surname matches ────────────────────────────
        // If multiple active projects share the same surname, sync requires
        // exact given-name match from the sheet to disambiguate them.
        $projectsBySurname = [];
        foreach ($projects as $p) {
            $pName = trim((string) $p->client_name);
            if (preg_match('/^(.+),\s+\S+(?:\s+\S+){0,2}\s*$/su', $pName, $sm)) {
                $pName = trim($sm[1]);
            }

            $surnameNorm = $this->normalizeClientSurname($pName);
            if ($surnameNorm === '') continue;

            $projectsBySurname[$surnameNorm][] = $p->id;
        }

        // ── Phase 1: Search every project across ALL tabs ─────────────────────
        // projectMatches[project_id][tabName] = ['workDays' => [...], 'effectiveClientName' => ...]
        $projectMatches         = [];
        $settlementClaimsPerTab = []; // tabName → groupKey → settNorm → project_id
        $projectsChecked        = 0;
        $today                  = date('Y-m-d');

        foreach ($projects as $project) {
            $projectsChecked++;

            $clientName = trim((string) $project->client_name);

            // Strip any ", Settlement" suffix added by previous syncs.
            $currentSettlement  = null;
            $matchingClientName = $clientName;
            if (preg_match('/^(.+),\s+(\S+(?:\s+\S+){0,2})\s*$/su', $clientName, $csm)) {
                $matchingClientName = trim($csm[1]);
                $currentSettlement  = trim($csm[2]);
            }

            $clientNameNorm = $this->normalizeClientSurname($matchingClientName);
            $projectGivenNameNorm = $this->normalizeClientGivenName($matchingClientName);
            $requiresGivenName = count($projectsBySurname[$clientNameNorm] ?? []) > 1;
            $kw             = $this->extractKeywords($matchingClientName);
            $keywords       = $kw['keywords'];
            $normalizedKws  = $kw['normalized'];

            foreach ($dataRowsPerTab as $tabName => $dataRows) {
                if (empty($dataRows)) continue;

                // ── 1. Collect all matching rows in this tab ──────────────────
                $allMatchingRows = [];
                foreach ($dataRows as $cols) {
                    $colC = trim((string) ($cols[2] ?? ''));
                    if (in_array($this->normalizeUkr($colC), $skipWords, true)) continue;
                    if ($colC === '') {
                        if (!empty($allMatchingRows)) $allMatchingRows[] = $cols;
                        continue;
                    }
                    if ($this->colCMatchesClient($colC, $clientNameNorm, $projectGivenNameNorm, $requiresGivenName, $keywords, $normalizedKws)) {
                        $allMatchingRows[] = $cols;
                    }
                }

                // Remove trailing empty-C rows
                while (!empty($allMatchingRows) && trim((string) (end($allMatchingRows)[2] ?? '')) === '') {
                    array_pop($allMatchingRows);
                }

                if (empty($allMatchingRows)) continue;

                // ── 2. Settlement disambiguation (per tab) ────────────────────
                // DEBUG
                if ($project->id === 1068 && $tabName === 'Шевченко') {
                    $this->line("DEBUG #1068+Шевченко matchRows=" . count($allMatchingRows));
                    foreach ($allMatchingRows as $ri => $c) {
                        $this->line("  row$ri: B=" . ($c[1]??'') . " C=" . ($c[2]??'') . " D=" . ($c[3]??''));
                    }
                }
                $groupKey     = mb_strtolower($matchingClientName) . '|' . $tabName;
                $isDuplicated = count($duplicateGroupsPerTab[$tabName][$groupKey] ?? []) > 1;
                $effectiveClientName = $matchingClientName;

                if ($isDuplicated || $currentSettlement !== null) {
                    $rowSettlements = [];
                    $lastSett       = '';
                    foreach ($allMatchingRows as $ri => $cols) {
                        $d = trim((string) ($cols[3] ?? ''));
                        if ($d !== '') $lastSett = $d;
                        $rowSettlements[$ri] = $lastSett;
                    }

                    $allSettlements = array_unique(
                        array_filter(array_values($rowSettlements), fn ($s) => $s !== '')
                    );

                    if (!empty($allSettlements)) {
                        if ($currentSettlement !== null) {
                            $targetSettNorm = $this->normalizeUkr($currentSettlement);
                            $settlementClaimsPerTab[$tabName][$groupKey][$targetSettNorm] ??= $project->id;
                        } else {
                            $myClaimed      = $settlementClaimsPerTab[$tabName][$groupKey] ?? [];
                            $mySettlement   = null;
                            $targetSettNorm = null;
                            foreach ($allSettlements as $s) {
                                $sNorm = $this->normalizeUkr($s);
                                if (!isset($myClaimed[$sNorm])) {
                                    $mySettlement   = $s;
                                    $targetSettNorm = $sNorm;
                                    break;
                                }
                            }
                            // All settlements in this tab taken — skip this tab for this project.
                            if ($mySettlement === null) continue;

                            $settlementClaimsPerTab[$tabName][$groupKey][$targetSettNorm] = $project->id;
                            $effectiveClientName = $matchingClientName . ', ' . $mySettlement;
                        }

                        $filtered = [];
                        foreach ($allMatchingRows as $ri => $cols) {
                            if ($this->normalizeUkr($rowSettlements[$ri]) === $targetSettNorm) {
                                $filtered[] = $cols;
                            }
                        }
                        if (!empty($filtered)) $allMatchingRows = $filtered;
                    }
                }

                // ── 3. Group matching rows into consecutive date blocks ────────
                $blocks = [];
                $block  = [];

                foreach ($allMatchingRows as $cols) {
                    $rawDate = trim((string) ($cols[1] ?? ''));
                    $date    = $rawDate !== '' ? $this->normalizeDate($rawDate) : null;

                    if ($date === null) {
                        if (!empty($block)) $block[] = $cols;
                        continue;
                    }

                    if (empty($block)) { $block[] = $cols; continue; }

                    $lastDate = null;
                    for ($bi = count($block) - 1; $bi >= 0; $bi--) {
                        $ld = $this->normalizeDate(trim((string) ($block[$bi][1] ?? '')));
                        if ($ld !== null) { $lastDate = $ld; break; }
                    }

                    if ($lastDate !== null) {
                        $diff = (int) round(
                            (\Carbon\Carbon::parse($date)->timestamp - \Carbon\Carbon::parse($lastDate)->timestamp) / 86400
                        );
                        if ($diff <= 1 && $diff >= 0) { $block[] = $cols; continue; }
                    }

                    $blocks[] = $block;
                    $block    = [$cols];
                }
                if (!empty($block)) $blocks[] = $block;
                if (empty($blocks)) continue;

                // ── 4. Best block: earliest upcoming, else most recent past ────
                $bestBlock = null;
                foreach ($blocks as $b) {
                    $firstDate = $this->normalizeDate(trim((string) ($b[0][1] ?? '')));
                    if ($firstDate !== null && $firstDate >= $today) { $bestBlock = $b; break; }
                }
                if ($bestBlock === null) {
                    foreach (array_reverse($blocks) as $b) {
                        $firstDate = $this->normalizeDate(trim((string) ($b[0][1] ?? '')));
                        if ($firstDate !== null) { $bestBlock = $b; break; }
                    }
                }
                if ($bestBlock === null) continue;

                // Remove trailing empty-C rows from best block
                while (!empty($bestBlock) && trim((string) (end($bestBlock)[2] ?? '')) === '') {
                    array_pop($bestBlock);
                }
                if (empty($bestBlock)) continue;

                // ── 5. Parse work days ────────────────────────────────────────
                $workDays = [];
                foreach ($bestBlock as $cols) {
                    $rawDate = trim((string) ($cols[1] ?? ''));
                    if ($rawDate === '') continue;
                    $date = $this->normalizeDate($rawDate);
                    if (!$date) {
                        Log::warning('sheets:sync-installers: invalid date',
                            ['client' => $effectiveClientName, 'sheet' => $tabName, 'raw' => $rawDate]);
                        $this->warn("  Невалідна дата '{$rawDate}' для '{$effectiveClientName}' на листі '{$tabName}'");
                        continue;
                    }
                    $workDays[] = [
                        'date'        => $date,
                        'description' => trim((string) ($cols[4] ?? '')),
                        'notes'       => trim((string) ($cols[6] ?? '')),
                    ];
                }
                if (empty($workDays)) continue;

                // ── 6. Record match for this tab ──────────────────────────────
                $projectMatches[$project->id][$tabName] = [
                    'workDays'            => $workDays,
                    'effectiveClientName' => $effectiveClientName,
                ];
            }
        }

        // ── Phase 2: Update projects + build schedule entries ─────────────────
        $scheduleRows    = [];
        $projectsUpdated = 0;

        foreach ($projects as $project) {
            $matches = $projectMatches[$project->id] ?? [];

            if (empty($matches)) {
                Log::info('sheets:sync-installers: client not found in any tab',
                    ['client' => $project->client_name]);
                continue;
            }

            // Build installation_team: sorted unique tab names, comma-separated.
            $matchedTabs = array_keys($matches);
            sort($matchedTabs);
            $newInstallationTeam = implode(', ', $matchedTabs);

            // Best panel_work_start_date: earliest upcoming across all matched tabs.
            $bestWorkDays  = null;
            $bestStartDate = null;
            foreach ($matches as $match) {
                $firstDate = $match['workDays'][0]['date'];
                if ($firstDate >= $today && ($bestStartDate === null || $firstDate < $bestStartDate)) {
                    $bestStartDate = $firstDate;
                    $bestWorkDays  = $match['workDays'];
                }
            }
            if ($bestWorkDays === null) {
                // All in the past — take most recent
                foreach ($matches as $match) {
                    $firstDate = $match['workDays'][0]['date'];
                    if ($bestStartDate === null || $firstDate > $bestStartDate) {
                        $bestStartDate = $firstDate;
                        $bestWorkDays  = $match['workDays'];
                    }
                }
            }

            $startDate = $bestWorkDays[0]['date'];
            $endDate   = end($bestWorkDays)['date'];
            $days      = count($bestWorkDays);

            $taskNote = '';
            foreach ($bestWorkDays as $wd) {
                if ($wd['description'] !== '') { $taskNote = $wd['description']; break; }
            }
            $noteText = '';
            foreach ($bestWorkDays as $wd) {
                if ($wd['notes'] !== '') { $noteText = $wd['notes']; break; }
            }

            // Use effectiveClientName from first matched tab (lowest tab name alphabetically).
            $effectiveClientName = $matches[$matchedTabs[0]]['effectiveClientName'];

            $update = [
                'installation_team'     => $newInstallationTeam,
                'panel_work_start_date' => $startDate,
                'panel_work_days'       => $days,
                'updated_at'            => now(),
            ];
            if ($effectiveClientName !== trim((string) $project->client_name)) {
                $update['client_name'] = $effectiveClientName;
            }
            if ($taskNote !== '' && $taskNote !== ($project->installation_team_task_note ?? '')) {
                $update['installation_team_task_note'] = $taskNote;
            }
            if ($noteText !== '' && $noteText !== ($project->installation_team_note ?? '')) {
                $update['installation_team_note'] = $noteText;
            }

            DB::table('sales_projects')->where('id', $project->id)->update($update);
            $projectsUpdated++;

            $endLabel   = $days > 1 ? " → {$endDate} ({$days} дн.)" : '';
            $teamsLabel = implode('+', $matchedTabs);
            $this->line("  [{$teamsLabel}] '{$effectiveClientName}': {$startDate}{$endLabel}" . ($taskNote ? " — {$taskNote}" : ''));

            // ── Build schedule entries for EACH matched tab ───────────────────
            if (!Schema::hasTable('project_schedule_entries')) continue;

            foreach ($matches as $tabName => $match) {
                $clientKey = mb_strtolower(trim($match['effectiveClientName']));

                foreach ($match['workDays'] as $wd) {
                    $key = "{$clientKey}|installation_team|{$tabName}|{$wd['date']}";

                    // Lower-id project wins on deduplication
                    if (isset($scheduleRows[$key]) && $scheduleRows[$key]['project_id'] < $project->id) {
                        continue;
                    }

                    $description = $wd['description'];
                    if ($wd['notes'] !== '') {
                        $description .= ($description ? "\n" : '') . 'Примітки: ' . $wd['notes'];
                    }

                    $scheduleRows[$key] = [
                        'project_id'       => $project->id,
                        'assignment_field' => 'installation_team',
                        'assignment_value' => $tabName,
                        'work_date'        => $wd['date'],
                        'work_description' => $description ?: null,
                        'source'           => 'google_sheet',
                        'created_at'       => now(),
                        'updated_at'       => now(),
                    ];
                }
            }
        }

        // ── Upsert project_schedule_entries ───────────────────────────────────
        if (Schema::hasTable('project_schedule_entries') && !empty($scheduleRows)) {
            $teamValues = array_values(array_unique(
                array_map(fn ($r) => $r['assignment_value'], $scheduleRows)
            ));

            DB::table('project_schedule_entries')
                ->where('assignment_field', 'installation_team')
                ->whereIn('assignment_value', $teamValues)
                ->delete();

            DB::table('project_schedule_entries')->insertOrIgnore(array_values($scheduleRows));
        }

        Log::info('sheets:sync-installers done', [
            'projects_checked' => $projectsChecked,
            'projects_updated' => $projectsUpdated,
            'schedule_entries' => count($scheduleRows),
        ]);

        $this->info("Done. Checked: {$projectsChecked}, updated: {$projectsUpdated}, schedule entries: " . count($scheduleRows));

        return self::SUCCESS;
    }
}
