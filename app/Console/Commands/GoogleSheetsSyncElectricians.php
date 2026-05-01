<?php

namespace App\Console\Commands;

use App\Services\GoogleSheetsService;
use App\Services\ScheduleChangeNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class GoogleSheetsSyncElectricians extends Command
{
    protected $signature = 'sheets:sync-electricians';

    protected $description = 'Sync electrician work dates from Google Sheets to operational projects and service requests';

    public function handle(): int
    {
        $serviceAvailable = Schema::hasTable('service_requests');
        $scheduleAvailable = Schema::hasTable('project_schedule_entries');
        $serviceHasProjectId = $serviceAvailable && Schema::hasColumn('service_requests', 'project_id');
        $serviceHasAmoDealId = $serviceAvailable && Schema::hasColumn('service_requests', 'amo_deal_id');
        $serviceHasSource = $serviceAvailable && Schema::hasColumn('service_requests', 'source');

        $eligibleProjects = DB::table('sales_projects')
            ->select([
                'id',
                'client_name',
                'electrician',
                'electric_work_start_date',
                'electrician_note',
                'electrician_task_note',
                'amo_deal_id',
                'pipeline_id',
                'amo_deal_name',
                'amo_status_id',
            ])
            ->where('source_layer', 'projects')
            ->whereNotNull('electrician')
            ->where('electrician', '!=', '')
            ->where('electrician', '!=', 'Без монтажних робіт')
            ->orderBy('id')
            ->get();

        if ($eligibleProjects->isEmpty()) {
            $this->info('No projects-layer rows with electrician set — nothing to sync.');
            return self::SUCCESS;
        }

        $allProjects = DB::table('sales_projects')
            ->select([
                'id',
                'client_name',
                'electrician',
                'electric_work_start_date',
                'electrician_note',
                'electrician_task_note',
                'amo_deal_id',
                'pipeline_id',
                'amo_deal_name',
                'amo_status_id',
                'construction_status',
                'status',
            ])
            ->where('source_layer', 'projects')
            ->orderBy('id')
            ->get();

        $financeWithElectrician = DB::table('sales_projects')
            ->select(['id', 'client_name', 'amo_deal_id', 'amo_deal_name', 'electrician', 'electric_work_start_date'])
            ->where('source_layer', 'finance')
            ->whereNotNull('electrician')
            ->where('electrician', '!=', '')
            ->where('status', 'active')
            ->get();

        if ($financeWithElectrician->isNotEmpty()) {
            $projectsAmoIds = $eligibleProjects->pluck('amo_deal_id')->filter()->unique()->values()->all();
            foreach ($financeWithElectrician as $finRow) {
                Log::warning('sheets:sync-electricians:finance_row_excluded', [
                    'reason' => 'finance_source_layer_excluded_from_sync',
                    'command' => 'sheets:sync-electricians',
                    'finance_project_id' => (int) $finRow->id,
                    'client_name' => $finRow->client_name,
                    'electrician' => $finRow->electrician,
                    'amo_deal_id' => $finRow->amo_deal_id,
                    'amo_deal_name' => $finRow->amo_deal_name,
                    'electric_work_start_date' => $finRow->electric_work_start_date,
                    'has_projects_counterpart' => $finRow->amo_deal_id
                        ? in_array($finRow->amo_deal_id, $projectsAmoIds, true)
                        : false,
                ]);
            }
            $this->warn('⚠ ' . $financeWithElectrician->count() . ' finance rows with electrician excluded from sync (see logs).');
        }

        try {
            $sheets = new GoogleSheetsService();
        } catch (\Throwable $e) {
            Log::error('sheets:sync-electricians: GoogleSheetsService init failed', ['err' => $e->getMessage()]);
            $this->error('GoogleSheetsService init failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        $projectsById = $eligibleProjects->keyBy('id');
        $projectOutcomes = [];
        $serviceOnlyProjectIds = [];
        $serviceRequests = [];
        $processedElectricians = [];
        $touchedServiceIds = [];
        $sheetCache = [];
        $missingSheets = [];
        $skipWords = ['вихідні', 'вихідний', 'сервіси'];

        foreach ($eligibleProjects->pluck('electrician')->filter()->unique()->values() as $electricianName) {
            $electrician = trim((string) $electricianName);
            $sheetName = explode(' ', $electrician)[0] ?? $electrician;

            if (!isset($sheetCache[$sheetName])) {
                try {
                    $sheetCache[$sheetName] = $sheets->getSheetRows($sheetName, 'A:G');
                } catch (\Throwable $e) {
                    $missingSheets[] = $sheetName;
                    Log::warning('sheets:sync-electricians: sheet not found', [
                        'sheet' => $sheetName,
                        'err' => $e->getMessage(),
                    ]);
                    $this->warn("Sheet '{$sheetName}' not found: " . $e->getMessage());
                    continue;
                }
            }

            if (empty($sheetCache[$sheetName])) {
                continue;
            }

            $processedElectricians[$electrician] = true;
            $rows = $sheetCache[$sheetName];
            $startIndex = $this->detectStartIndex($rows);

            foreach (array_slice($rows, $startIndex) as $row) {
                $date = $this->normalizeDate($row[1] ?? '');
                $rawClients = trim((string) ($row[2] ?? ''));
                $rawSettlement = trim((string) ($row[3] ?? ''));
                $rawInstall = trim((string) ($row[4] ?? ''));
                $rawService = trim((string) ($row[5] ?? ''));
                $rawNote = trim((string) ($row[6] ?? ''));

                if (!$date || $rawClients === '') {
                    continue;
                }

                if (in_array(mb_strtolower($rawClients), $skipWords, true)) {
                    continue;
                }

                if ($rawInstall === '' && $rawService === '') {
                    continue;
                }

                foreach ($this->splitRowAssignments($rawClients, $rawSettlement, $rawInstall, $rawService, $rawNote) as $assignment) {
                    $clientText = $assignment['client'];
                    $installText = $assignment['install'];
                    $serviceText = $assignment['service'];

                    if ($clientText === '' || ($installText === '' && $serviceText === '')) {
                        continue;
                    }

                    $match = $this->matchProject(
                        $clientText,
                        $assignment['settlement'],
                        $electrician,
                        $eligibleProjects,
                        $allProjects,
                        $sheetName,
                        $date,
                        $rawClients
                    );

                    if ($installText !== '') {
                        if ($match['project'] === null) {
                            Log::warning('sheets:sync-electricians:no_candidate_project', [
                                'reason' => $match['reason'] ?: 'install_project_not_found',
                                'command' => 'sheets:sync-electricians',
                                'sheet' => $sheetName,
                                'electrician' => $electrician,
                                'scheduled_date' => $date,
                                'raw_client_text' => $rawClients,
                                'client_segment' => $clientText,
                                'alias' => $match['alias'],
                                'candidate_project_ids' => $match['candidate_ids'],
                                'candidate_amo_deal_ids' => $match['candidate_amo_ids'],
                            ]);
                        } else {
                            $project = $match['project'];
                            $projectOutcomes[$project->id][$date] = [
                                'install' => $installText,
                                'settlement' => $assignment['settlement'],
                                'note' => $rawNote,
                                'client_name' => $this->projectDisplayName($project, $assignment['settlement']),
                            ];
                        }
                    }

                    if ($serviceText !== '' && $serviceAvailable) {
                        $project = $match['project'];
                        if ($project !== null && $installText === '') {
                            $serviceOnlyProjectIds[(int) $project->id] = true;
                        }
                        $serviceRequests[] = [
                            'client_name' => $project ? $this->projectDisplayName($project, $assignment['settlement']) : $this->cleanClientText($clientText),
                            'settlement' => $assignment['settlement'] ?: '',
                            'electrician' => $electrician,
                            'description' => $rawNote !== '' ? $serviceText . "\nПримітки: " . $rawNote : $serviceText,
                            'scheduled_date' => $date,
                            'project_id' => $project ? (int) $project->id : null,
                            'amo_deal_id' => $project ? $project->amo_deal_id : null,
                            'raw_client_text' => $rawClients,
                            'client_segment' => $clientText,
                            'match_reason' => $match['reason'],
                        ];
                    }
                }
            }
        }

        $projectsUpdated = 0;
        $scheduleRows = [];
        $now = now();

        foreach ($eligibleProjects as $project) {
            $outcomes = $projectOutcomes[$project->id] ?? [];
            if (empty($outcomes)) {
                continue;
            }

            ksort($outcomes);
            $dates = array_keys($outcomes);
            $today = now()->toDateString();
            $futureDates = array_values(array_filter($dates, fn ($date) => $date >= $today));
            $startDate = $futureDates[0] ?? $dates[0];
            $first = $outcomes[$startDate];

            $update = [
                'electric_work_start_date' => $startDate,
                'updated_at' => $now,
            ];

            if ($first['client_name'] !== trim((string) $project->client_name)) {
                $update['client_name'] = $first['client_name'];
            }

            if ($first['install'] !== ($project->electrician_task_note ?? '')) {
                $update['electrician_task_note'] = $first['install'];
            }

            if ($first['note'] !== '' && $first['note'] !== ($project->electrician_note ?? '')) {
                $update['electrician_note'] = $first['note'];
            }

            DB::table('sales_projects')
                ->where('id', $project->id)
                ->where('source_layer', 'projects')
                ->update($update);
            $projectsUpdated++;

            if ($scheduleAvailable) {
                foreach ($outcomes as $date => $outcome) {
                    $key = "{$project->id}|electrician|{$project->electrician}|{$date}";
                    $scheduleRows[$key] = [
                        'project_id' => $project->id,
                        'assignment_field' => 'electrician',
                        'assignment_value' => $project->electrician,
                        'work_date' => $date,
                        'source' => 'google_sheet',
                        'work_description' => $outcome['install'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        foreach (array_keys($serviceOnlyProjectIds) as $projectId) {
            if (isset($projectOutcomes[$projectId])) {
                continue;
            }

            DB::table('sales_projects')
                ->where('id', $projectId)
                ->where('source_layer', 'projects')
                ->whereNotNull('electric_work_start_date')
                ->update([
                    'electric_work_start_date' => null,
                    'updated_at' => $now,
                ]);
        }

        if (!empty($processedElectricians)) {
            $processedElectricianNames = array_keys($processedElectricians);
            $expectedProjectIds = array_map('intval', array_keys($projectOutcomes));
            $today = now()->toDateString();

            $staleProjectQuery = DB::table('sales_projects')
                ->where('source_layer', 'projects')
                ->whereIn('electrician', $processedElectricianNames)
                ->whereNotNull('electric_work_start_date')
                ->where('electric_work_start_date', '>=', $today);

            if (!empty($expectedProjectIds)) {
                $staleProjectQuery->whereNotIn('id', $expectedProjectIds);
            }

            $staleProjectQuery->update([
                'electric_work_start_date' => null,
                'electrician_task_note' => null,
                'updated_at' => $now,
            ]);
        }

        $scheduleNotifications = 0;
        if ($scheduleAvailable && !empty($processedElectricians)) {
            $today = now()->toDateString();
            $scheduleNotifier = app(ScheduleChangeNotificationService::class);
            $oldSchedule = $scheduleNotifier->collectOldSchedule('electrician', array_keys($processedElectricians), $today);
            $newSchedule = $scheduleNotifier->collectNewSchedule($scheduleRows, 'electrician', $today);

            DB::table('project_schedule_entries')
                ->where('assignment_field', 'electrician')
                ->whereIn('assignment_value', array_keys($processedElectricians))
                ->where('work_date', '>=', $today)
                ->whereIn('source', ['google_sheet', 'automation'])
                ->delete();

            if (!empty($scheduleRows)) {
                DB::table('project_schedule_entries')->insertOrIgnore(array_values($scheduleRows));
            }

            $scheduleNotifications = $scheduleNotifier->notifyChangedSchedules('electrician', $oldSchedule, $newSchedule);
        }

        $servicesCreated = 0;
        $servicesUpdated = 0;

        if ($serviceAvailable) {
            foreach ($serviceRequests as $request) {
                $payload = [
                    'client_name' => $request['client_name'],
                    'settlement' => $request['settlement'],
                    'electrician' => $request['electrician'],
                    'description' => $request['description'],
                    'scheduled_date' => $request['scheduled_date'],
                    'status' => 'open',
                    'updated_at' => $now,
                ];

                if ($serviceHasProjectId) {
                    $payload['project_id'] = $request['project_id'];
                }
                if ($serviceHasAmoDealId) {
                    $payload['amo_deal_id'] = $request['amo_deal_id'];
                }
                if ($serviceHasSource) {
                    $payload['source'] = 'google_sheet';
                }

                $existing = $this->findExistingServiceRequest(
                    $request,
                    $serviceHasProjectId,
                    $serviceHasSource
                );

                if ($existing) {
                    if (($existing->status ?? null) !== 'open') {
                        unset($payload['status']);

                        Log::info('sheets:sync-electricians:service_request_existing_quality_preserved', [
                            'service_request_id' => $existing->id,
                            'status' => $existing->status ?? null,
                            'client_name' => $request['client_name'],
                            'electrician' => $request['electrician'],
                            'scheduled_date' => $request['scheduled_date'],
                            'description' => $request['description'],
                            'project_id' => $request['project_id'],
                        ]);
                    }

                    DB::table('service_requests')->where('id', $existing->id)->update($payload);
                    $touchedServiceIds[] = $existing->id;
                    $servicesUpdated++;
                } else {
                    $payload['created_at'] = $now;
                    $payload['created_by'] = null;
                    $newId = DB::table('service_requests')->insertGetId($payload);
                    $touchedServiceIds[] = $newId;
                    $servicesCreated++;
                }

                if ($request['project_id'] === null) {
                    Log::warning('sheets:sync-electricians:standalone_service_request', [
                        'reason' => $request['match_reason'] ?: 'service_project_not_found',
                        'command' => 'sheets:sync-electricians',
                        'electrician' => $request['electrician'],
                        'scheduled_date' => $request['scheduled_date'],
                        'raw_client_text' => $request['raw_client_text'],
                        'client_segment' => $request['client_segment'],
                    ]);
                }
            }
        }

        $servicesClosed = 0;
        if ($serviceAvailable && $serviceHasSource && !empty($processedElectricians)) {
            $stale = DB::table('service_requests')
                ->whereIn('electrician', array_keys($processedElectricians))
                ->where('status', 'open')
                ->where('source', 'google_sheet')
                ->whereNotExists(function ($qualityQuery) {
                    $qualityQuery
                        ->select(DB::raw(1))
                        ->from('quality_checks')
                        ->whereColumn('quality_checks.service_request_id', 'service_requests.id')
                        ->where('quality_checks.status', 'pending');
                })
                ->where(function ($query) use ($today, $touchedServiceIds) {
                    $query->where('scheduled_date', '<', $today);

                    if (!empty($touchedServiceIds)) {
                        $query->orWhere(function ($futureQuery) use ($today, $touchedServiceIds) {
                            $futureQuery
                                ->where('scheduled_date', '>=', $today)
                                ->whereNotIn('id', $touchedServiceIds);
                        });
                    } else {
                        $query->orWhere('scheduled_date', '>=', $today);
                    }
                })
                ->get(['id', 'client_name', 'electrician', 'scheduled_date']);

            foreach ($stale as $serviceRequest) {
                DB::table('service_requests')->where('id', $serviceRequest->id)->update([
                    'status' => 'closed',
                    'updated_at' => $now,
                ]);
                $servicesClosed++;
                Log::info('sheets:sync-electricians: closed stale service_request', [
                    'id' => $serviceRequest->id,
                    'client_name' => $serviceRequest->client_name,
                    'electrician' => $serviceRequest->electrician,
                    'scheduled_date' => $serviceRequest->scheduled_date,
                    'reason' => $serviceRequest->scheduled_date < $today
                        ? 'past_google_sheet_service'
                        : 'not_touched_in_current_sync',
                ]);
            }
        }

        Log::info('sheets:sync-electricians done', [
            'projects_checked' => $eligibleProjects->count(),
            'projects_updated' => $projectsUpdated,
            'schedule_entries' => count($scheduleRows),
            'services_created' => $servicesCreated,
            'services_updated' => $servicesUpdated,
            'services_closed' => $servicesClosed,
            'schedule_notifications' => $scheduleNotifications,
            'missing_sheets' => $missingSheets,
        ]);

        $this->info("Done. Checked: {$eligibleProjects->count()}, updated: {$projectsUpdated}, schedule entries: " . count($scheduleRows) . ", services created: {$servicesCreated}, updated: {$servicesUpdated}, stale closed: {$servicesClosed}, schedule notifications: {$scheduleNotifications}");

        return self::SUCCESS;
    }

    private function detectStartIndex(array $rows): int
    {
        $firstCell = trim((string) ($rows[0][0] ?? ''));

        if ($firstCell !== '' && !is_numeric($firstCell) && $this->normalizeDate($firstCell) === null && mb_strlen($firstCell) < 20) {
            return 1;
        }

        return 0;
    }

    private function normalizeDate(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }

        if (preg_match('/^(\d{1,2})[.\/-](\d{1,2})[.\/-](\d{2}|\d{4})$/', $value, $matches)) {
            $day = (int) $matches[1];
            $month = (int) $matches[2];
            $year = (int) $matches[3];
            if ($year < 100) {
                $year += 2000;
            }

            return checkdate($month, $day, $year) ? sprintf('%04d-%02d-%02d', $year, $month, $day) : null;
        }

        if (is_numeric($value) && (int) $value > 0) {
            try {
                return \Carbon\Carbon::create(1899, 12, 30)->addDays((int) $value)->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        }

        try {
            return \Carbon\Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function splitRowAssignments(string $clients, string $settlements, string $install, string $service, string $note): array
    {
        $clientParts = $this->splitParts($clients);
        $settlementParts = $this->splitParts($settlements);
        $installParts = $this->splitParts($install);
        $serviceParts = $this->splitParts($service);
        $clientCount = count($clientParts);
        $installCount = count($installParts);
        $serviceCount = count($serviceParts);
        $assignments = [];

        foreach ($clientParts as $index => $client) {
            $rowInstall = '';
            $rowService = '';

            if ($installCount > 0 && $serviceCount > 0) {
                if ($clientCount === 1) {
                    $rowInstall = $installParts[0] ?? '';
                    $rowService = $serviceParts[0] ?? '';
                } elseif ($clientCount === 2 && $installCount === 1) {
                    $rowInstall = $index === 0 ? ($installParts[0] ?? '') : '';
                    $rowService = $index === 1 ? ($serviceParts[0] ?? '') : '';
                } elseif ($index < $installCount) {
                    $rowInstall = $installParts[$index] ?? $installParts[$installCount - 1];
                } else {
                    $serviceIndex = $index - $installCount;
                    $rowService = $serviceParts[$serviceIndex] ?? $serviceParts[$serviceCount - 1];
                }
            } elseif ($installCount > 0) {
                $rowInstall = $installParts[$index] ?? $installParts[$installCount - 1];
            } elseif ($serviceCount > 0) {
                $rowService = $serviceParts[$index] ?? $serviceParts[$serviceCount - 1];
            }

            $assignments[] = [
                'client' => $client,
                'settlement' => $settlementParts[$index] ?? ($settlementParts[count($settlementParts) - 1] ?? ''),
                'install' => $rowInstall,
                'service' => $rowService,
                'note' => $note,
            ];
        }

        return $assignments;
    }

    private function matchProject(string $clientText, string $settlement, string $electrician, $eligibleProjects, $allProjects, string $sheet, string $date, string $rawClients): array
    {
        $identity = $this->parseClientIdentity($clientText);
        $eligible = $eligibleProjects
            ->filter(fn ($project) => trim((string) $project->electrician) === $electrician)
            ->filter(fn ($project) => $this->identityMatchesProject($identity, $project))
            ->values();

        $siblings = $allProjects
            ->filter(fn ($project) => $this->identityMatchesProject($identity, $project))
            ->values();

        $result = [
            'project' => null,
            'reason' => null,
            'alias' => $identity['alias'],
            'candidate_ids' => $eligible->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            'candidate_amo_ids' => $eligible->pluck('amo_deal_id')->filter()->values()->all(),
        ];

        if ($eligible->isEmpty()) {
            $result['reason'] = 'no_candidate_project';
            return $result;
        }

        if ($eligible->count() === 1) {
            $project = $eligible->first();
            $result['project'] = $project;
            if ($identity['alias'] !== '' && !$this->aliasMatchesProject($identity['alias'], $project)) {
                $result['reason'] = 'alias_not_found_but_identity_unique';
                Log::warning('sheets:sync-electricians:alias_not_found_but_identity_unique', [
                    'reason' => 'identity_unique_alias_not_confirmed',
                    'command' => 'sheets:sync-electricians',
                    'sheet' => $sheet,
                    'electrician' => $electrician,
                    'scheduled_date' => $date,
                    'raw_client_text' => $rawClients,
                    'client_segment' => $clientText,
                    'alias' => $identity['alias'],
                    'project_id' => (int) $project->id,
                    'amo_deal_id' => $project->amo_deal_id,
                    'amo_deal_name' => $project->amo_deal_name,
                ]);
            }
            return $result;
        }

        if ($identity['alias'] !== '') {
            $eligibleAliasMatches = $eligible->filter(fn ($project) => $this->aliasMatchesProject($identity['alias'], $project))->values();
            $siblingAliasMatches = $siblings->filter(fn ($project) => $this->aliasMatchesProject($identity['alias'], $project))->values();

            if ($eligibleAliasMatches->count() === 1) {
                $result['project'] = $eligibleAliasMatches->first();
                $result['reason'] = 'alias_match';
                return $result;
            }

            if ($eligibleAliasMatches->count() > 1) {
                $result['reason'] = 'ambiguous_alias_match';
                Log::warning('sheets:sync-electricians:ambiguous_alias_match', [
                    'reason' => 'alias_matches_multiple_eligible_projects',
                    'command' => 'sheets:sync-electricians',
                    'sheet' => $sheet,
                    'electrician' => $electrician,
                    'scheduled_date' => $date,
                    'raw_client_text' => $rawClients,
                    'client_segment' => $clientText,
                    'alias' => $identity['alias'],
                    'candidate_project_ids' => $eligibleAliasMatches->pluck('id')->values()->all(),
                    'candidate_amo_deal_ids' => $eligibleAliasMatches->pluck('amo_deal_id')->filter()->values()->all(),
                    'candidate_amo_deal_names' => $eligibleAliasMatches->pluck('amo_deal_name')->filter()->values()->all(),
                ]);
                return $result;
            }

            if ($siblingAliasMatches->isNotEmpty()) {
                $result['reason'] = 'alias_matches_noneligible_project';
                Log::warning('sheets:sync-electricians:alias_matches_noneligible_project', [
                    'reason' => 'alias_belongs_to_project_outside_writable_electrician_scope',
                    'command' => 'sheets:sync-electricians',
                    'sheet' => $sheet,
                    'electrician' => $electrician,
                    'scheduled_date' => $date,
                    'raw_client_text' => $rawClients,
                    'client_segment' => $clientText,
                    'alias' => $identity['alias'],
                    'eligible_candidates' => $eligible->map(fn ($project) => $this->projectLogPayload($project))->values()->all(),
                    'matching_siblings' => $siblingAliasMatches->map(fn ($project) => $this->projectLogPayload($project))->values()->all(),
                ]);
                return $result;
            }

            $result['reason'] = 'alias_not_found_in_amo_deal_name';
            Log::warning('sheets:sync-electricians:alias_not_found_in_amo_deal_name', [
                'reason' => 'identity_ambiguous_alias_not_found',
                'command' => 'sheets:sync-electricians',
                'sheet' => $sheet,
                'electrician' => $electrician,
                'scheduled_date' => $date,
                'raw_client_text' => $rawClients,
                'client_segment' => $clientText,
                'alias' => $identity['alias'],
                'eligible_candidates' => $eligible->map(fn ($project) => $this->projectLogPayload($project))->values()->all(),
                'all_siblings' => $siblings->map(fn ($project) => $this->projectLogPayload($project))->values()->all(),
            ]);
            return $result;
        }

        if ($settlement !== '') {
            $settlementMatches = $eligible
                ->filter(fn ($project) => str_contains($this->normName((string) $project->client_name), $this->normName($settlement)))
                ->values();
            if ($settlementMatches->count() === 1) {
                $result['project'] = $settlementMatches->first();
                $result['reason'] = 'settlement_match';
                return $result;
            }
        }

        $result['reason'] = 'ambiguous_identity_without_alias';
        Log::warning('sheets:sync-electricians:ambiguous_identity_without_alias', [
            'reason' => 'multiple_projects_match_identity_without_alias',
            'command' => 'sheets:sync-electricians',
            'sheet' => $sheet,
            'electrician' => $electrician,
            'scheduled_date' => $date,
            'raw_client_text' => $rawClients,
            'client_segment' => $clientText,
            'settlement' => $settlement,
            'eligible_candidates' => $eligible->map(fn ($project) => $this->projectLogPayload($project))->values()->all(),
        ]);

        return $result;
    }

    private function identityMatchesProject(array $identity, object $project): bool
    {
        $projectIdentity = $this->parseClientIdentity((string) $project->client_name);

        foreach (['surname', 'first_name', 'patronymic'] as $field) {
            if ($identity[$field] !== '' && $identity[$field] !== $projectIdentity[$field]) {
                return false;
            }
        }

        return $identity['surname'] !== '';
    }

    private function parseClientIdentity(string $value): array
    {
        $alias = '';
        if (preg_match('/\(([^)]*)\)/u', $value, $matches)) {
            $alias = trim($matches[1]);
        }

        $withoutAlias = trim((string) preg_replace('/\([^)]*\)/u', ' ', $value));
        $withoutSettlement = trim(explode(',', $withoutAlias, 2)[0]);
        $tokens = array_values(array_filter(preg_split('/\s+/u', $this->normName($withoutSettlement)) ?: []));

        return [
            'surname' => $tokens[0] ?? '',
            'first_name' => $tokens[1] ?? '',
            'patronymic' => $tokens[2] ?? '',
            'alias' => $alias,
        ];
    }

    private function aliasMatchesProject(string $alias, object $project): bool
    {
        $aliasNorm = $this->normName($alias);
        if ($aliasNorm === '') {
            return false;
        }

        $haystack = $this->normName(implode(' ', array_filter([
            (string) ($project->amo_deal_name ?? ''),
            (string) ($project->client_name ?? ''),
        ])));

        return $haystack !== '' && str_contains($haystack, $aliasNorm);
    }

    private function projectDisplayName(object $project, string $settlement): string
    {
        $name = trim((string) $project->client_name);
        if ($settlement === '') {
            return $name;
        }

        $base = trim(explode(',', $name, 2)[0]);
        if (str_contains($this->normName($name), $this->normName($settlement))) {
            return $name;
        }

        return $base . ', ' . $settlement;
    }

    private function cleanClientText(string $clientText): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', preg_replace('/\([^)]*\)/u', ' ', $clientText)));
    }

    private function splitParts(string $value): array
    {
        return array_values(array_filter(array_map('trim', explode('/', $value)), fn ($part) => $part !== ''));
    }

    private function normName(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = str_replace(["\u{02BC}", "\u{2019}", "\u{2018}", "'"], '', $value);
        return (string) preg_replace('/\s+/u', ' ', trim($value));
    }

    private function projectLogPayload(object $project): array
    {
        return [
            'project_id' => (int) $project->id,
            'client_name' => $project->client_name,
            'electrician' => $project->electrician,
            'amo_deal_id' => $project->amo_deal_id,
            'amo_deal_name' => $project->amo_deal_name,
            'construction_status' => $project->construction_status ?? null,
            'status' => $project->status ?? null,
        ];
    }

    private function findExistingServiceRequest(array $request, bool $serviceHasProjectId, bool $serviceHasSource): ?object
    {
        if ($serviceHasProjectId && $request['project_id'] !== null) {
            $linked = DB::table('service_requests')
                ->where('project_id', $request['project_id'])
                ->where('electrician', $request['electrician'])
                ->where('scheduled_date', $request['scheduled_date'])
                ->where('description', $request['description']);

            if ($serviceHasSource) {
                $linked->where('source', 'google_sheet');
            } else {
                $linked->whereNull('created_by');
            }

            $this->whereServiceRequestSyncMatchIsActive($linked);

            $existing = $this->orderServiceRequestSyncMatches($linked)->first();
            if ($existing) {
                return $existing;
            }
        }

        $legacy = DB::table('service_requests')
            ->where('client_name', $request['client_name'])
            ->where('electrician', $request['electrician'])
            ->where('scheduled_date', $request['scheduled_date'])
            ->where('description', $request['description']);

        if ($serviceHasProjectId) {
            $legacy->whereNull('project_id');
        }
        if ($serviceHasSource) {
            $legacy->where('source', 'google_sheet');
        } else {
            $legacy->whereNull('created_by');
        }

        $this->whereServiceRequestSyncMatchIsActive($legacy);

        return $this->orderServiceRequestSyncMatches($legacy)->first();
    }

    private function whereServiceRequestSyncMatchIsActive($query): void
    {
        $query->where(function ($statusQuery) {
            $statusQuery
                ->whereIn('status', ['open', 'waiting_quality_check'])
                ->orWhereExists(function ($qualityQuery) {
                    $qualityQuery
                        ->select(DB::raw(1))
                        ->from('quality_checks')
                        ->whereColumn('quality_checks.service_request_id', 'service_requests.id')
                        ->whereIn('quality_checks.status', ['pending', 'approved']);
                });
        });
    }

    private function orderServiceRequestSyncMatches($query)
    {
        return $query
            ->orderByRaw("
                CASE
                    WHEN status = 'waiting_quality_check' THEN 0
                    WHEN EXISTS (
                        SELECT 1
                        FROM quality_checks
                        WHERE quality_checks.service_request_id = service_requests.id
                          AND quality_checks.status = 'pending'
                    ) THEN 1
                    WHEN EXISTS (
                        SELECT 1
                        FROM quality_checks
                        WHERE quality_checks.service_request_id = service_requests.id
                          AND quality_checks.status = 'approved'
                    ) THEN 2
                    WHEN status = 'open' THEN 3
                    ELSE 4
                END
            ")
            ->orderByDesc('id');
    }
}
