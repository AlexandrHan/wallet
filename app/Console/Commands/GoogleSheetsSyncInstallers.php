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
        // Ukrainian letter equivalences that vary between sources
        $text = str_replace(['ї', 'і', 'є'], ['и', 'и', 'е'], $text);
        // Gendered suffix variations
        $text = str_replace(['ська', 'зька', 'цька'], ['ска', 'зка', 'цка'], $text);
        $text = str_replace(['ський', 'зький', 'цький'], ['ский', 'зкий', 'цкий'], $text);
        return $text;
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
     * Check whether a sheet column-C value matches a project client.
     * Priority: exact (normalized) → keyword contains → similar_text
     */
    private function colCMatchesClient(
        string $colC,
        string $clientNameNorm,
        array  $keywords,
        array  $normalizedKeywords
    ): bool {
        if ($colC === '') return false;

        $colCNorm = $this->normalizeUkr($colC);

        // 1. Exact normalized match
        if ($colCNorm === $clientNameNorm) return true;

        // 2. Any keyword appears inside colC (or vice-versa)
        foreach ($normalizedKeywords as $kw) {
            if (mb_strlen($kw) >= 4 && mb_strpos($colCNorm, $kw) !== false) return true;
        }
        foreach ($normalizedKeywords as $kw) {
            if (mb_strlen($kw) >= 4 && mb_strpos($kw, $colCNorm) !== false) return true;
        }

        // 3. similar_text fallback (≥ 68%)
        similar_text($clientNameNorm, $colCNorm, $pct);
        return $pct >= 68.0;
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

        $projects = DB::table('sales_projects')
            ->select([
                'id', 'client_name', 'installation_team',
                'panel_work_start_date', 'panel_work_days',
                'installation_team_note', 'installation_team_task_note',
            ])
            ->whereNotNull('installation_team')
            ->where('installation_team', '!=', '')
            ->where('installation_team', '!=', 'Без монтажних робіт')
            ->where('status', '!=', 'completed')
            ->get();

        if ($projects->isEmpty()) {
            $this->info('No active projects with installation_team — nothing to sync.');
            return self::SUCCESS;
        }

        try {
            $sheets = new GoogleSheetsService();
        } catch (\Throwable $e) {
            Log::error('sheets:sync-installers: GoogleSheetsService init failed', ['err' => $e->getMessage()]);
            $this->error('GoogleSheetsService init failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        $sheetCache    = [];   // tabName → rows[]
        $missingSheets = [];
        $scheduleRows  = [];   // keyed "{project_id}|{team}|{date}" to deduplicate

        $projectsChecked = 0;
        $projectsUpdated = 0;

        foreach ($projects as $project) {
            $projectsChecked++;

            $team    = trim((string) $project->installation_team);
            $tabName = explode(' ', $team)[0];

            // ── 1. Load sheet tab (cached) ────────────────────────────────────
            if (!isset($sheetCache[$tabName])) {
                if (in_array($tabName, $missingSheets, true)) continue;

                try {
                    $sheetCache[$tabName] = $sheets->getSheetRows($tabName, 'A:G');
                } catch (\Throwable $e) {
                    $missingSheets[] = $tabName;
                    Log::warning('sheets:sync-installers: sheet not found',
                        ['sheet' => $tabName, 'err' => $e->getMessage()]);
                    $this->warn("Вкладка '{$tabName}' не знайдена: " . $e->getMessage());
                    continue;
                }
            }

            $allRows = $sheetCache[$tabName];
            if (empty($allRows)) continue;

            // Skip header row if first cell looks like a label
            $startIndex = 0;
            $firstCell  = trim((string) ($allRows[0][0] ?? ''));
            if ($firstCell !== '' && !is_numeric($firstCell)
                && $this->normalizeDate($firstCell) === null
                && mb_strlen($firstCell) < 20) {
                $startIndex = 1;
            }

            $dataRows = array_slice($allRows, $startIndex);

            // ── 2. Prepare matching data ──────────────────────────────────────
            $clientName     = trim((string) $project->client_name);
            $clientNameNorm = $this->normalizeUkr($clientName);
            $kw             = $this->extractKeywords($clientName);
            $keywords       = $kw['keywords'];
            $normalizedKws  = $kw['normalized'];

            // ── 3. Find first matching row (scan full sheet) ──────────────────
            $firstMatchIdx = null;

            foreach ($dataRows as $idx => $cols) {
                $colC = trim((string) ($cols[2] ?? ''));
                if ($colC === '') continue;
                if (in_array($this->normalizeUkr($colC), $skipWords, true)) continue;

                if ($this->colCMatchesClient($colC, $clientNameNorm, $keywords, $normalizedKws)) {
                    $firstMatchIdx = $idx;
                    break;
                }
            }

            if ($firstMatchIdx === null) {
                Log::info('sheets:sync-installers: client not found', [
                    'client' => $clientName, 'sheet' => $tabName,
                ]);
                continue;
            }

            // ── 4. Collect all consecutive rows for this client ───────────────
            // Rows must be consecutive (no other client between them).
            // An empty C column row is treated as a continuation (blank = same block).
            $matchedRows = [];

            for ($i = $firstMatchIdx; $i < count($dataRows); $i++) {
                $cols = $dataRows[$i];
                $colC = trim((string) ($cols[2] ?? ''));

                if ($colC === '') {
                    // Empty C = continuation of previous block (blank merged cell)
                    if (!empty($matchedRows)) {
                        $matchedRows[] = $cols;
                    }
                    continue;
                }

                if (in_array($this->normalizeUkr($colC), $skipWords, true)) break;

                if ($this->colCMatchesClient($colC, $clientNameNorm, $keywords, $normalizedKws)) {
                    $matchedRows[] = $cols;
                } else {
                    // Different client → stop
                    break;
                }
            }

            // Remove trailing rows with empty C (they were added speculatively)
            while (!empty($matchedRows) && trim((string) (end($matchedRows)[2] ?? '')) === '') {
                array_pop($matchedRows);
            }

            if (empty($matchedRows)) continue;

            // ── 5. Parse dates from each matched row ──────────────────────────
            $workDays = []; // [{date, description, notes}]

            foreach ($matchedRows as $cols) {
                $rawDate = trim((string) ($cols[1] ?? '')); // B = date
                $colE    = trim((string) ($cols[4] ?? '')); // E = works
                $colG    = trim((string) ($cols[6] ?? '')); // G = notes

                if ($rawDate === '') continue;

                $date = $this->normalizeDate($rawDate);
                if (!$date) {
                    Log::warning('sheets:sync-installers: invalid date', [
                        'client' => $clientName, 'sheet' => $tabName, 'raw' => $rawDate,
                    ]);
                    $this->warn("  Невалідна дата '{$rawDate}' для '{$clientName}' на листі '{$tabName}'");
                    continue;
                }

                $workDays[] = [
                    'date'        => $date,
                    'description' => $colE,
                    'notes'       => $colG,
                ];
            }

            if (empty($workDays)) continue;

            // ── 6. Determine start date, end date, days count ─────────────────
            $startDate = $workDays[0]['date'];
            $endDate   = end($workDays)['date'];
            $days      = count($workDays);

            // task_note = first non-empty description (summary for project card)
            $taskNote = '';
            foreach ($workDays as $wd) {
                if ($wd['description'] !== '') { $taskNote = $wd['description']; break; }
            }

            // notes = first non-empty notes field
            $noteText = '';
            foreach ($workDays as $wd) {
                if ($wd['notes'] !== '') { $noteText = $wd['notes']; break; }
            }

            // ── 7. Update project ─────────────────────────────────────────────
            $update = [
                'panel_work_start_date' => $startDate,
                'panel_work_days'       => $days,
                'updated_at'            => now(),
            ];

            if ($taskNote !== '' && $taskNote !== ($project->installation_team_task_note ?? '')) {
                $update['installation_team_task_note'] = $taskNote;
            }
            if ($noteText !== '' && $noteText !== ($project->installation_team_note ?? '')) {
                $update['installation_team_note'] = $noteText;
            }

            DB::table('sales_projects')->where('id', $project->id)->update($update);
            $projectsUpdated++;

            $endLabel = ($days > 1) ? " → {$endDate} ({$days} дн.)" : '';
            $this->line("  [{$team}] '{$clientName}': {$startDate}{$endLabel}" . ($taskNote ? " — {$taskNote}" : ''));

            // ── 8. Build schedule entries (one per actual work day) ───────────
            if (Schema::hasTable('project_schedule_entries')) {
                foreach ($workDays as $wd) {
                    $key = "{$project->id}|installation_team|{$team}|{$wd['date']}";
                    $description = $wd['description'];
                    if ($wd['notes'] !== '') {
                        $description .= ($description ? "\n" : '') . 'Примітки: ' . $wd['notes'];
                    }

                    $scheduleRows[$key] = [
                        'project_id'       => $project->id,
                        'assignment_field' => 'installation_team',
                        'assignment_value' => $team,
                        'work_date'        => $wd['date'],
                        'work_description' => $description ?: null,
                        'source'           => 'google_sheet',
                        'created_at'       => now(),
                        'updated_at'       => now(),
                    ];
                }
            }
        }

        // ── 9. Upsert project_schedule_entries ────────────────────────────────
        if (Schema::hasTable('project_schedule_entries') && !empty($scheduleRows)) {
            $teamValues = array_values(array_unique(
                array_map(fn ($r) => $r['assignment_value'], $scheduleRows)
            ));

            DB::table('project_schedule_entries')
                ->where('assignment_field', 'installation_team')
                ->whereIn('assignment_value', $teamValues)
                ->where('source', 'like', 'google_sheet%')
                ->delete();

            DB::table('project_schedule_entries')->insertOrIgnore(array_values($scheduleRows));
        }

        Log::info('sheets:sync-installers done', [
            'projects_checked'  => $projectsChecked,
            'projects_updated'  => $projectsUpdated,
            'schedule_entries'  => count($scheduleRows),
        ]);

        $this->info("Done. Checked: {$projectsChecked}, updated: {$projectsUpdated}, schedule entries: " . count($scheduleRows));

        return self::SUCCESS;
    }
}
