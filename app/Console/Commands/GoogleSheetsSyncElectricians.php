<?php

namespace App\Console\Commands;

use App\Services\GoogleSheetsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class GoogleSheetsSyncElectricians extends Command
{
    protected $signature = 'sheets:sync-electricians';

    protected $description = 'Sync electrician work dates from Google Sheets to sales_projects';

    public function handle(): int
    {
        $normalizeDate = function ($value): ?string {
            $value = trim((string) $value);
            if ($value === '') return null;
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return $value;
            if (preg_match('/^(\d{1,2})[.\/-](\d{1,2})[.\/-](\d{2}|\d{4})$/', $value, $m)) {
                $d = (int) $m[1]; $mo = (int) $m[2]; $y = (int) $m[3];
                if ($y < 100) $y += 2000;
                if (checkdate($mo, $d, $y)) return sprintf('%04d-%02d-%02d', $y, $mo, $d);
            }
            if (is_numeric($value) && (int) $value > 0) {
                try { return \Carbon\Carbon::create(1899, 12, 30)->addDays((int) $value)->format('Y-m-d'); }
                catch (\Throwable) { return null; }
            }
            try { return \Carbon\Carbon::parse($value)->format('Y-m-d'); } catch (\Throwable) { return null; }
        };

        // Normalize a name string for matching: lowercase, strip all apostrophe variants, collapse spaces.
        // Handles: ' (U+0027), ' (U+2019), ʼ (U+02BC) — common in Ukrainian names like Товстоп'ят.
        $normName = static function (string $s): string {
            $s = mb_strtolower(trim($s));
            $s = str_replace(["\u{02BC}", "\u{2019}", "\u{2018}", "'"], '', $s);
            return (string) preg_replace('/\s+/u', ' ', trim($s));
        };

        // Extract the leading surname portion from a normalized segment.
        // Splits on any non-letter character so "казидуб(спочатку до нього)" → "казидуб".
        $segSurname = static fn (string $s): string =>
            preg_split('/[^\p{L}]+/u', $s, 2, PREG_SPLIT_NO_EMPTY)[0] ?? '';

        $serviceAvailable  = Schema::hasTable('service_requests');
        $scheduleAvailable = Schema::hasTable('project_schedule_entries');
        $skipWords         = ['вихідні', 'вихідний', 'сервіси'];

        $projects = DB::table('sales_projects')
            ->select(['id', 'client_name', 'electrician', 'electric_work_start_date',
                      'electrician_note', 'electrician_task_note'])
            ->whereNotNull('electrician')
            ->where('electrician', '!=', '')
            ->where('electrician', '!=', 'Без монтажних робіт')
            ->orderBy('id')
            ->get();

        if ($projects->isEmpty()) {
            $this->info('No projects with electrician set — nothing to sync.');
            return self::SUCCESS;
        }

        try {
            $sheets = new GoogleSheetsService();
        } catch (\Throwable $e) {
            Log::error('sheets:sync-electricians: GoogleSheetsService init failed', ['err' => $e->getMessage()]);
            $this->error('GoogleSheetsService init failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        $sheetCache          = [];
        $missingSheets       = [];
        $scheduleRows        = [];
        $serviceOnlyPairs    = [];
        $settlementClaims    = [];   // {clientLow}|{electrician} => {settlementLow => project_id}
        $touchedServiceIds   = [];   // IDs of service_requests updated/created in this run
        $processedElectricians = []; // electricians whose sheets were successfully loaded

        // Pre-compute duplicate groups: same client_name + electrician → multiple projects.
        // Strip any ", Settlement" suffix added by previous syncs before grouping.
        $duplicateGroups = [];
        foreach ($projects as $p) {
            $pName = trim((string) $p->client_name);
            if (preg_match('/^(.+),\s+\S+(?:\s+\S+){0,2}\s*$/su', $pName, $dsm)) {
                $pName = trim($dsm[1]);
            }
            $dKey = mb_strtolower($pName) . '|' . trim((string) $p->electrician);
            $duplicateGroups[$dKey][] = $p->id;
        }

        $settNorm = fn (string $s) => mb_strtolower(trim($s));

        $projectsChecked  = 0;
        $projectsUpdated  = 0;
        $servicesCreated  = 0;
        $servicesUpdated  = 0;

        foreach ($projects as $project) {
            $projectsChecked++;

            $electrician = trim((string) $project->electrician);
            $surname     = explode(' ', $electrician)[0];

            if (!isset($sheetCache[$surname])) {
                if (in_array($surname, $missingSheets, true)) {
                    continue;
                }
                try {
                    $sheetCache[$surname] = $sheets->getSheetRows($surname, 'A:G');
                    $processedElectricians[$electrician] = true;
                } catch (\Throwable $e) {
                    $missingSheets[] = $surname;
                    Log::warning('sheets:sync-electricians: sheet not found',
                        ['sheet' => $surname, 'err' => $e->getMessage()]);
                    $this->warn("Sheet '{$surname}' not found: " . $e->getMessage());
                    continue;
                }
            } else {
                $processedElectricians[$electrician] = true;
            }

            $sheetRows = $sheetCache[$surname];
            if (empty($sheetRows)) continue;

            $startIndex = 0;
            $firstCell  = trim((string) ($sheetRows[0][0] ?? ''));
            if ($firstCell !== '' && !is_numeric($firstCell) && $normalizeDate($firstCell) === null
                && mb_strlen($firstCell) < 20) {
                $startIndex = 1;
            }

            $clientName = trim((string) $project->client_name);

            // Strip settlement suffix previously added by this sync (e.g. "Петренко, Канів").
            $currentSettlement  = null;
            $matchingClientName = $clientName;
            if (preg_match('/^(.+),\s+(\S+(?:\s+\S+){0,2})\s*$/su', $clientName, $csm)) {
                $matchingClientName = trim($csm[1]);
                $currentSettlement  = trim($csm[2]);
            }
            $matchingLow = mb_strtolower($matchingClientName); // kept for groupKey / settlement disambiguation
            $matchNorm   = $normName($matchingClientName);        // apostrophe-free normalized full name
            $matchFirst  = $segSurname($matchNorm);               // surname only, for segment comparison

            // Collect ALL matching rows for this client.
            $allMatchedRows  = [];
            $allFallbackRows = [];

            foreach (array_slice($sheetRows, $startIndex) as $cols) {
                $colC = trim((string) ($cols[2] ?? ''));
                if ($colC === '') continue;
                if (in_array(mb_strtolower($colC), $skipWords, true)) continue;
                // Normalize every segment of colC (strips apostrophes, lowercases).
                $colCSegsNorm  = array_values(array_filter(
                    array_map(fn ($s) => $normName($s), explode('/', $colC)),
                    fn ($s) => $s !== ''
                ));
                // First-word (surname) of each normed segment — handles "Казидуб(Спочатку...)" → "казидуб".
                $colCFirstWords = array_map($segSurname, $colCSegsNorm);
                // Three tiers: full name == segment | surname == segment | surname == segment's first word.
                if (!in_array($matchNorm,  $colCSegsNorm,   true)
                    && !in_array($matchFirst, $colCSegsNorm,   true)
                    && !in_array($matchFirst, $colCFirstWords, true)) continue;

                $hasWork = trim((string) ($cols[4] ?? '')) !== ''
                        || trim((string) ($cols[5] ?? '')) !== '';
                if ($hasWork) $allMatchedRows[] = $cols;
                else $allFallbackRows[] = $cols;
            }
            if (empty($allMatchedRows)) $allMatchedRows = $allFallbackRows;

            // similar_text fallback
            if (empty($allMatchedRows)) {
                $bestScore = 0.0;
                $bestRow   = null;
                foreach (array_slice($sheetRows, $startIndex) as $cols) {
                    $colC = trim((string) ($cols[2] ?? ''));
                    if ($colC === '') continue;
                    if (in_array(mb_strtolower($colC), $skipWords, true)) continue;
                    // Compare surname from DB against surname portion of each normed segment.
                    foreach (array_values(array_filter(
                        array_map(fn ($s) => $normName($s), explode('/', $colC)),
                        fn ($s) => $s !== ''
                    )) as $seg) {
                        $cmp = $segSurname($seg) ?: $seg;
                        similar_text($matchFirst, $cmp, $pct);
                        if ($pct > $bestScore) { $bestScore = (float) $pct; $bestRow = $cols; }
                    }
                }
                if ($bestScore >= 72.0) $allMatchedRows = [$bestRow];
            }

            if (empty($allMatchedRows)) {
                Log::info('sheets:sync-electricians: client not found on sheet',
                    ['client' => $clientName, 'sheet' => $surname]);
                continue;
            }

            // ── Settlement disambiguation → $workingRows ──────────────────────────
            // When multiple projects share the same client+electrician, use colD
            // (settlement) to assign each project to its own subset of matched rows.
            $groupKey     = $matchingLow . '|' . $electrician;
            $isDuplicated = count($duplicateGroups[$groupKey] ?? []) > 1;
            $workingRows  = $allMatchedRows;

            if ($isDuplicated || $currentSettlement !== null) {
                // For multi-client rows colD contains "/" — extract only this client's
                // settlement portion so we don't claim "Черкаси/Шевченкове" as one unit.
                $rowSettlements = array_map(
                    function ($cols) use ($normName, $segSurname, $matchNorm, $matchFirst) {
                        $colDRaw = trim((string) ($cols[3] ?? ''));
                        if ($colDRaw === '') return '';
                        $dParts = array_values(array_filter(
                            array_map('trim', explode('/', $colDRaw)), fn ($s) => $s !== ''
                        ));
                        if (count($dParts) <= 1) return $colDRaw;
                        // Multi-settlement colD — pick this client's portion by cIdx.
                        $colCSegs = array_values(array_filter(
                            array_map('trim', explode('/', trim((string) ($cols[2] ?? '')))),
                            fn ($s) => $s !== ''
                        ));
                        $colCNorm = array_map(fn ($s) => $normName($s), $colCSegs);
                        $ci = array_search($matchNorm, $colCNorm);
                        if ($ci === false) {
                            $ci = array_search($matchFirst, array_map($segSurname, $colCNorm));
                        }
                        $ci = $ci === false ? 0 : $ci;
                        return $dParts[$ci] ?? $dParts[count($dParts) - 1];
                    },
                    $allMatchedRows
                );
                $allSettlements = array_unique(array_filter($rowSettlements, fn ($s) => $s !== ''));

                if (!empty($allSettlements)) {
                    if ($currentSettlement !== null) {
                        // Already assigned — keep only rows matching this settlement.
                        $targetNorm = $settNorm($currentSettlement);
                        if (!isset($settlementClaims[$groupKey][$targetNorm])) {
                            $settlementClaims[$groupKey][$targetNorm] = $project->id;
                        }
                        $workingRows = [];
                        foreach ($allMatchedRows as $ri => $cols) {
                            if ($settNorm($rowSettlements[$ri]) === $targetNorm) {
                                $workingRows[] = $cols;
                            }
                        }
                    } else {
                        // Claim first unclaimed settlement.
                        $myClaimed       = $settlementClaims[$groupKey] ?? [];
                        $claimedSett     = null;
                        $claimedSettNorm = null;
                        foreach ($allSettlements as $s) {
                            $sn = $settNorm($s);
                            if (!isset($myClaimed[$sn])) { $claimedSett = $s; $claimedSettNorm = $sn; break; }
                        }
                        if ($claimedSett === null) {
                            // All settlements taken — clear stale date and skip.
                            DB::table('sales_projects')->where('id', $project->id)->update([
                                'electric_work_start_date' => null,
                                'updated_at'               => now(),
                            ]);
                            continue;
                        }
                        $settlementClaims[$groupKey][$claimedSettNorm] = $project->id;
                        $clientName  = $matchingClientName . ', ' . $claimedSett;
                        $workingRows = [];
                        foreach ($allMatchedRows as $ri => $cols) {
                            if ($settNorm($rowSettlements[$ri]) === $claimedSettNorm) {
                                $workingRows[] = $cols;
                            }
                        }
                    }
                }
            }

            if (empty($workingRows)) continue;

            // ── Per-row outcome extraction ────────────────────────────────────────
            // Iterates ALL working rows (one project can span multiple dates).
            // For the same date, multi-client rows (colC contains "/") take priority
            // over single-client rows so that task assignment is always correct.
            //
            // Google Sheets multi-client rules (colE = монтаж, colF = сервіс):
            //
            //   1 клієнт:
            //     E є          → монтаж
            //     F є          → сервіс
            //     E + F        → монтаж + сервіс для того ж клієнта
            //
            //   2 клієнти:
            //     E є, F є     → клієнт[0] = монтаж, клієнт[1] = сервіс
            //     тільки E     → обидва = монтаж
            //     тільки F     → обидва = сервіс
            //
            //   3–4 клієнти:
            //     E є, F немає → усі = монтаж (остання частина E повторюється)
            //     E є, F є     → перші len(E) = монтаж, решта = сервіс з F
            //     тільки F     → усі = сервіс
            //
            //   Колонка D — позиційне мапування client[i] → settlement[i].
            //   Якщо частин у D менше — береться остання.
            $outcomesByDate = []; // date => [colCCount, install, service, settlement, colG]

            foreach ($workingRows as $row) {
                $colB = trim((string) ($row[1] ?? ''));
                $colD = trim((string) ($row[3] ?? ''));
                $colE = trim((string) ($row[4] ?? ''));
                $colF = trim((string) ($row[5] ?? ''));
                $colG = trim((string) ($row[6] ?? ''));

                $rowDate = $normalizeDate($colB);
                if (!$rowDate) {
                    Log::warning('sheets:sync-electricians: invalid date',
                        ['client' => $clientName, 'sheet' => $surname, 'raw' => $colB]);
                    $this->warn("Invalid date '{$colB}' for '{$clientName}' on sheet '{$surname}'");
                    continue;
                }

                $colCRaw  = trim((string) ($row[2] ?? ''));
                $colCSegs = array_values(array_filter(
                    array_map('trim', explode('/', $colCRaw)),
                    fn ($s) => $s !== ''
                ));
                $colCCount = count($colCSegs);

                // Locate this client's position in the colC segment list.
                $colCSegsForIdx = array_map(fn ($s) => $normName($s), $colCSegs);
                $cIdx = array_search($matchNorm, $colCSegsForIdx);
                if ($cIdx === false) {
                    $cIdx = array_search($matchFirst, array_map($segSurname, $colCSegsForIdx));
                }
                if ($cIdx === false) $cIdx = 0;

                $eParts = array_values(array_filter(array_map('trim', explode('/', $colE)), fn ($s) => $s !== ''));
                $fParts = array_values(array_filter(array_map('trim', explode('/', $colF)), fn ($s) => $s !== ''));
                $dParts = array_values(array_filter(array_map('trim', explode('/', $colD)), fn ($s) => $s !== ''));
                $eCount = count($eParts); $fCount = count($fParts); $dCount = count($dParts);

                $rowSettlement = $dParts[$cIdx] ?? ($dCount > 0 ? $dParts[$dCount - 1] : $colD);

                if ($eCount > 0) {
                    if ($cIdx < $eCount) {
                        $rowInstall = $eParts[$cIdx]; $rowService = '';
                    } elseif ($fCount === 0) {
                        // E exists but no F — overflow clients also get монтаж.
                        $rowInstall = $eParts[$eCount - 1]; $rowService = '';
                    } else {
                        // Client is past the E zone → falls into сервіс from F.
                        $rowInstall = ''; $svcIdx = $cIdx - $eCount;
                        $rowService = $fParts[$svcIdx] ?? $fParts[$fCount - 1];
                    }
                } else {
                    $rowInstall = '';
                    $rowService = $fCount > 0 ? $fParts[min($cIdx, $fCount - 1)] : '';
                }

                if ($rowInstall === '' && $rowService === '') continue;

                // Same date: prefer multi-client rows (higher colCCount wins).
                if (isset($outcomesByDate[$rowDate]) && $colCCount <= $outcomesByDate[$rowDate]['colCCount']) {
                    continue;
                }

                $outcomesByDate[$rowDate] = [
                    'colCCount'  => $colCCount,
                    'install'    => $rowInstall,
                    'service'    => $rowService,
                    'settlement' => $rowSettlement,
                    'colG'       => $colG,
                ];
            }

            if (empty($outcomesByDate)) continue;

            // ── Determine electric_work_start_date ───────────────────────────────
            // Earliest future install date; falls back to earliest past install date.
            $todayStr     = now()->toDateString();
            $installDates = array_values(array_filter(
                array_keys($outcomesByDate),
                fn ($d) => $outcomesByDate[$d]['install'] !== ''
            ));
            sort($installDates);
            $futureInstall  = array_values(array_filter($installDates, fn ($d) => $d >= $todayStr));
            $newStartDate   = !empty($futureInstall) ? $futureInstall[0] : ($installDates[0] ?? null);
            $hasInstall     = $newStartDate !== null;
            $firstInstallOc = $hasInstall ? $outcomesByDate[$newStartDate] : null;

            // ── Update sales_projects (once per project) ─────────────────────────
            if ($hasInstall) {
                $update = ['electric_work_start_date' => $newStartDate, 'updated_at' => now()];
                if ($clientName !== trim((string) $project->client_name)) {
                    $update['client_name'] = $clientName;
                }
                $taskNote = $firstInstallOc['install'];
                if ($taskNote !== ($project->electrician_task_note ?? '')) {
                    $update['electrician_task_note'] = $taskNote;
                }
                $colGNote = $firstInstallOc['colG'];
                if ($colGNote !== '' && $colGNote !== ($project->electrician_note ?? '')) {
                    $update['electrician_note'] = $colGNote;
                }
                DB::table('sales_projects')->where('id', $project->id)->update($update);
                $projectsUpdated++;
                $this->line("Updated '{$clientName}' ({$electrician}): {$newStartDate} — {$taskNote}");
            } else {
                // Service-only — clear install date and schedule entries.
                DB::table('sales_projects')
                    ->where('id', $project->id)
                    ->whereNotNull('electric_work_start_date')
                    ->update(['electric_work_start_date' => null, 'updated_at' => now()]);
                if ($scheduleAvailable) {
                    $serviceOnlyPairs[] = ['project_id' => $project->id, 'assignment_value' => $electrician];
                }
            }

            // ── Schedule entries — one per install date ───────────────────────────
            if ($scheduleAvailable && $hasInstall) {
                foreach ($outcomesByDate as $d => $oc) {
                    if ($oc['install'] === '') continue;
                    $key = "{$project->id}|electrician|{$electrician}|{$d}";
                    $scheduleRows[$key] = [
                        'project_id'       => $project->id,
                        'assignment_field' => 'electrician',
                        'assignment_value' => $electrician,
                        'work_date'        => $d,
                        'source'           => 'google_sheet',
                        'created_at'       => now(),
                        'updated_at'       => now(),
                    ];
                }
            }

            // ── Service requests — one per service date ───────────────────────────
            if ($serviceAvailable) {
                foreach ($outcomesByDate as $d => $oc) {
                    if ($oc['service'] === '') continue;

                    $description = $oc['service'];
                    if ($oc['colG'] !== '') $description .= "\nПримітки: " . $oc['colG'];

                    $servicePayload = [
                        'client_name'    => $clientName,
                        'settlement'     => $oc['settlement'] ?: '',
                        'electrician'    => $electrician,
                        'description'    => $description,
                        'scheduled_date' => $d,
                        'status'         => 'open',
                        'updated_at'     => now(),
                    ];

                    // Match by date too — a project can have services on different dates.
                    $existing = DB::table('service_requests')
                        ->where('client_name', $clientName)
                        ->where('electrician', $electrician)
                        ->where('scheduled_date', $d)
                        ->where('status', 'open')
                        ->orderByDesc('id')
                        ->first();

                    if ($existing) {
                        DB::table('service_requests')->where('id', $existing->id)->update($servicePayload);
                        $touchedServiceIds[] = $existing->id;
                        $servicesUpdated++;
                    } else {
                        $servicePayload['created_at'] = now();
                        $servicePayload['created_by'] = null;
                        $newId = DB::table('service_requests')->insertGetId($servicePayload);
                        $touchedServiceIds[] = $newId;
                        $servicesCreated++;
                    }
                }
            }
        }

        if ($scheduleAvailable && !empty($serviceOnlyPairs)) {
            foreach ($serviceOnlyPairs as $pair) {
                DB::table('project_schedule_entries')
                    ->where('assignment_field', 'electrician')
                    ->where('assignment_value', $pair['assignment_value'])
                    ->where('project_id', $pair['project_id'])
                    ->delete();
            }
        }

        if ($scheduleAvailable && !empty($scheduleRows)) {
            $electricianValues = array_values(array_unique(
                array_map(fn ($r) => $r['assignment_value'], $scheduleRows)
            ));

            DB::table('project_schedule_entries')
                ->where('assignment_field', 'electrician')
                ->whereIn('assignment_value', $electricianValues)
                ->where('source', 'like', 'google_sheet%')
                ->delete();

            DB::table('project_schedule_entries')->insertOrIgnore(array_values($scheduleRows));
        }

        // ── Stale service_request cleanup ────────────────────────────────────
        // Close open service_requests for processed electricians where:
        //   1. scheduled_date is strictly in the past
        //   2. the record was NOT updated/created in this sync run
        // This removes ghost services no longer present in the sheet.
        $servicesClosed = 0;
        if ($serviceAvailable && !empty($processedElectricians)) {
            $electricianNames = array_keys($processedElectricians);
            $today            = now()->toDateString();

            $stale = DB::table('service_requests')
                ->whereIn('electrician', $electricianNames)
                ->where('status', 'open')
                ->whereNull('created_by')  // only sheet-synced records; manual ones (created_by != null) are never auto-closed
                ->when(!empty($touchedServiceIds), fn ($q) => $q->whereNotIn('id', $touchedServiceIds))
                ->get(['id', 'client_name', 'electrician', 'scheduled_date']);

            foreach ($stale as $sr) {
                DB::table('service_requests')->where('id', $sr->id)->update([
                    'status'     => 'closed',
                    'updated_at' => now(),
                ]);
                $servicesClosed++;
                Log::info('sheets:sync-electricians: closed stale service_request', [
                    'id'             => $sr->id,
                    'client_name'    => $sr->client_name,
                    'electrician'    => $sr->electrician,
                    'scheduled_date' => $sr->scheduled_date,
                ]);
                $this->line("Closed stale service: [{$sr->id}] {$sr->client_name} ({$sr->electrician}) {$sr->scheduled_date}");
            }
        }

        Log::info('sheets:sync-electricians done', [
            'projects_checked'  => $projectsChecked,
            'projects_updated'  => $projectsUpdated,
            'services_created'  => $servicesCreated,
            'services_updated'  => $servicesUpdated,
            'services_closed'   => $servicesClosed,
        ]);

        $this->info("Done. Checked: {$projectsChecked}, updated: {$projectsUpdated}, services created: {$servicesCreated}, updated: {$servicesUpdated}, stale closed: {$servicesClosed}");

        return self::SUCCESS;
    }
}
