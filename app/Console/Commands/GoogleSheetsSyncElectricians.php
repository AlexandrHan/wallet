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

        $sheetCache       = [];
        $missingSheets    = [];
        $scheduleRows     = [];
        $serviceOnlyPairs = [];
        $settlementClaims = [];   // {clientLow}|{electrician} => {settlementLow => project_id}

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
                } catch (\Throwable $e) {
                    $missingSheets[] = $surname;
                    Log::warning('sheets:sync-electricians: sheet not found',
                        ['sheet' => $surname, 'err' => $e->getMessage()]);
                    $this->warn("Sheet '{$surname}' not found: " . $e->getMessage());
                    continue;
                }
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
            $matchingLow = mb_strtolower($matchingClientName);

            // Collect ALL matching rows for this client.
            $allMatchedRows  = [];
            $allFallbackRows = [];

            foreach (array_slice($sheetRows, $startIndex) as $cols) {
                $colC = trim((string) ($cols[2] ?? ''));
                if ($colC === '') continue;
                if (in_array(mb_strtolower($colC), $skipWords, true)) continue;
                if (mb_strtolower($colC) !== $matchingLow) continue;

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
                    similar_text($matchingLow, mb_strtolower($colC), $pct);
                    if ($pct > $bestScore) { $bestScore = (float) $pct; $bestRow = $cols; }
                }
                if ($bestScore >= 72.0) $allMatchedRows = [$bestRow];
            }

            if (empty($allMatchedRows)) {
                Log::info('sheets:sync-electricians: client not found on sheet',
                    ['client' => $clientName, 'sheet' => $surname]);
                continue;
            }

            // Settlement disambiguation: when multiple projects share client+electrician,
            // use colD to assign each project to a distinct row.
            $groupKey     = $matchingLow . '|' . $electrician;
            $isDuplicated = count($duplicateGroups[$groupKey] ?? []) > 1;
            $matchedRow   = $allMatchedRows[0];

            if ($isDuplicated || $currentSettlement !== null) {
                $rowSettlements = array_map(fn ($cols) => trim((string) ($cols[3] ?? '')), $allMatchedRows);
                $allSettlements = array_unique(array_filter($rowSettlements, fn ($s) => $s !== ''));

                if (!empty($allSettlements)) {
                    if ($currentSettlement !== null) {
                        // Already assigned — filter to this settlement's row.
                        $targetNorm = $settNorm($currentSettlement);
                        if (!isset($settlementClaims[$groupKey][$targetNorm])) {
                            $settlementClaims[$groupKey][$targetNorm] = $project->id;
                        }
                        foreach ($allMatchedRows as $ri => $cols) {
                            if ($settNorm($rowSettlements[$ri]) === $targetNorm) {
                                $matchedRow = $cols;
                                break;
                            }
                        }
                    } else {
                        // Claim first unclaimed settlement.
                        $myClaimed    = $settlementClaims[$groupKey] ?? [];
                        $mySettlement = null;
                        $myNorm       = null;
                        foreach ($allSettlements as $s) {
                            $sn = $settNorm($s);
                            if (!isset($myClaimed[$sn])) { $mySettlement = $s; $myNorm = $sn; break; }
                        }
                        if ($mySettlement === null) {
                            // All settlements taken — clear stale date and skip.
                            DB::table('sales_projects')->where('id', $project->id)->update([
                                'electric_work_start_date' => null,
                                'updated_at'               => now(),
                            ]);
                            continue;
                        }
                        $settlementClaims[$groupKey][$myNorm] = $project->id;
                        $clientName = $matchingClientName . ', ' . $mySettlement;
                        foreach ($allMatchedRows as $ri => $cols) {
                            if ($settNorm($rowSettlements[$ri]) === $myNorm) {
                                $matchedRow = $cols;
                                break;
                            }
                        }
                    }
                }
            }

            $colB = trim((string) ($matchedRow[1] ?? ''));
            $colD = trim((string) ($matchedRow[3] ?? ''));
            $colE = trim((string) ($matchedRow[4] ?? ''));
            $colF = trim((string) ($matchedRow[5] ?? ''));
            $colG = trim((string) ($matchedRow[6] ?? ''));

            if ($colE === '' && $colF === '') continue;

            $date = $normalizeDate($colB);
            if (!$date) {
                Log::warning('sheets:sync-electricians: invalid date',
                    ['client' => $clientName, 'sheet' => $surname, 'raw' => $colB]);
                $this->warn("Invalid date '{$colB}' for '{$clientName}' on sheet '{$surname}'");
                continue;
            }

            if ($colE !== '') {
                $update = ['electric_work_start_date' => $date, 'updated_at' => now()];
                if ($clientName !== trim((string) $project->client_name)) {
                    $update['client_name'] = $clientName;
                }
                if ($colE !== ($project->electrician_task_note ?? '')) {
                    $update['electrician_task_note'] = $colE;
                }
                if ($colG !== '' && $colG !== ($project->electrician_note ?? '')) {
                    $update['electrician_note'] = $colG;
                }

                DB::table('sales_projects')->where('id', $project->id)->update($update);
                $projectsUpdated++;
                $this->line("Updated '{$clientName}' ({$electrician}): {$date} — {$colE}");

                if ($scheduleAvailable) {
                    $key = "{$project->id}|electrician|{$electrician}|{$date}";
                    $scheduleRows[$key] = [
                        'project_id'       => $project->id,
                        'assignment_field' => 'electrician',
                        'assignment_value' => $electrician,
                        'work_date'        => $date,
                        'source'           => 'google_sheet',
                        'created_at'       => now(),
                        'updated_at'       => now(),
                    ];
                }
            }

            if ($colF !== '' && $serviceAvailable) {
                $description = $colF;
                if ($colG !== '') $description .= "\nПримітки: {$colG}";

                $servicePayload = [
                    'client_name'    => $clientName,
                    'settlement'     => $colD ?: '',
                    'electrician'    => $electrician,
                    'description'    => $description,
                    'scheduled_date' => $date,
                    'status'         => 'open',
                    'updated_at'     => now(),
                ];

                $existing = DB::table('service_requests')
                    ->where('client_name', $clientName)
                    ->where('electrician', $electrician)
                    ->where('status', 'open')
                    ->orderByDesc('id')
                    ->first();

                if ($existing) {
                    DB::table('service_requests')->where('id', $existing->id)->update($servicePayload);
                    $servicesUpdated++;
                } else {
                    $servicePayload['created_at'] = now();
                    $servicePayload['created_by'] = null;
                    DB::table('service_requests')->insert($servicePayload);
                    $servicesCreated++;
                }

                if ($colE === '') {
                    DB::table('sales_projects')
                        ->where('id', $project->id)
                        ->whereNotNull('electric_work_start_date')
                        ->update(['electric_work_start_date' => null, 'updated_at' => now()]);
                    if ($scheduleAvailable) {
                        $serviceOnlyPairs[] = ['project_id' => $project->id, 'assignment_value' => $electrician];
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

        Log::info('sheets:sync-electricians done', [
            'projects_checked'  => $projectsChecked,
            'projects_updated'  => $projectsUpdated,
            'services_created'  => $servicesCreated,
            'services_updated'  => $servicesUpdated,
        ]);

        $this->info("Done. Checked: {$projectsChecked}, updated: {$projectsUpdated}, services created: {$servicesCreated}, updated: {$servicesUpdated}");

        return self::SUCCESS;
    }
}
