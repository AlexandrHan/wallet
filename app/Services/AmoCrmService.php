<?php

namespace App\Services;

use App\Models\AmoCrmDealMap;
use App\Models\AmoComplectationProject;
use App\Models\AmoCrmToken;
use App\Models\SalesProject;
use App\Models\User;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AmoCrmService
{
    private const EVENT_LEAD_ADD = 'lead.add';
    private const EVENT_LEAD_UPDATE = 'lead.update';
    private const EVENT_LEAD_WON = 'lead.won';

    public function exchangeAuthorizationCode(string $authorizationCode): array
    {
        $response = Http::timeout(30)
            ->acceptJson()
            ->post($this->oauthTokenUrl(), [
                'client_id' => (string) config('services.amocrm.client_id'),
                'client_secret' => (string) config('services.amocrm.client_secret'),
                'grant_type' => 'authorization_code',
                'code' => $authorizationCode,
                'redirect_uri' => (string) config('services.amocrm.redirect_uri'),
            ]);

        if (!$response->successful()) {
            Log::error('amoCRM auth code exchange failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'ok' => false,
                'status' => $response->status(),
                'body' => $response->body(),
            ];
        }

        $token = $this->storeTokenPayload($response->json());

        return [
            'ok' => true,
            'expires_at' => optional($token->expires_at)->toDateTimeString(),
        ];
    }

    public function getAccessToken(): string
    {
        $token = AmoCrmToken::query()->latest('id')->first();

        if (!$token) {
            $token = $this->bootstrapTokenFromConfig();
        }

        if (!$token->expires_at || Carbon::parse($token->expires_at)->subMinute()->isPast()) {
            $token = $this->refreshAccessToken();
        }

        return (string) $token->access_token;
    }

    public function refreshAccessToken(): AmoCrmToken
    {
        $token = AmoCrmToken::query()->latest('id')->first();

        if (!$token?->refresh_token) {
            $token = $this->bootstrapTokenFromConfig();
        }

        $response = Http::timeout(30)
            ->acceptJson()
            ->post($this->oauthTokenUrl(), [
                'client_id' => (string) config('services.amocrm.client_id'),
                'client_secret' => (string) config('services.amocrm.client_secret'),
                'grant_type' => 'refresh_token',
                'refresh_token' => (string) $token->refresh_token,
                'redirect_uri' => (string) config('services.amocrm.redirect_uri'),
            ]);

        if (!$response->successful()) {
            Log::error('amoCRM token refresh failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \RuntimeException('amoCRM token refresh failed.');
        }

        return $this->storeTokenPayload($response->json());
    }

    public function fetchDeals(int $page = 1, int $limit = 250): array
    {
        $statusIds = config('services.amocrm.project_status_ids', []);
        $pipelineId = (int) config('services.amocrm.project_pipeline_id');

        $query = [
            'page' => $page,
            'limit' => $limit,
            'with' => 'contacts,users',
        ];

        foreach (array_values($statusIds) as $i => $statusId) {
            $query['filter[statuses][' . $i . '][pipeline_id]'] = $pipelineId;
            $query['filter[statuses][' . $i . '][status_id]'] = $statusId;
        }

        $response = $this->apiRequest('GET', '/leads', ['query' => $query]);

        return Arr::get($response->json(), '_embedded.leads', []);
    }

    public function fetchComplectationDeals(int $page = 1, int $limit = 250): array
    {
        $financeStageIds = $this->financeStageIds();
        $pipelineId = (int) config('services.amocrm.project_pipeline_id');

        $query = [
            'page' => $page,
            'limit' => $limit,
            'with' => 'contacts,users',
        ];

        $i = 0;
        foreach (array_values($financeStageIds) as $statusId) {
            $query['filter[statuses][' . $i . '][pipeline_id]'] = $pipelineId;
            $query['filter[statuses][' . $i . '][status_id]'] = $statusId;
            $i++;
        }

        foreach ($this->ntvReportStageIds() as $statusId) {
            if (in_array($statusId, $financeStageIds, true)) {
                continue;
            }

            $query['filter[statuses][' . $i . '][pipeline_id]'] = $pipelineId;
            $query['filter[statuses][' . $i . '][status_id]'] = $statusId;
            $i++;
        }

        // Also fetch finance stages from salary pipelines (e.g. retail pipeline)
        foreach ($this->salaryPipelines() as $salaryPipelineId => $meta) {
            foreach ((array) ($meta['finance_stage_ids'] ?? []) as $statusId) {
                if ((int) $statusId > 0) {
                    $query['filter[statuses][' . $i . '][pipeline_id]'] = $salaryPipelineId;
                    $query['filter[statuses][' . $i . '][status_id]'] = (int) $statusId;
                    $i++;
                }
            }
        }

        $response = $this->apiRequest('GET', '/leads', ['query' => $query]);

        return Arr::get($response->json(), '_embedded.leads', []);
    }

    public function fetchSalaryWonDeals(int $pipelineId, int $page = 1, int $limit = 250): array
    {
        $wonStatusId = (int) config('services.amocrm.won_status_id', 142);

        $response = $this->apiRequest('GET', '/leads', [
            'query' => [
                'page' => $page,
                'limit' => $limit,
                'with' => 'contacts,users',
                'filter[statuses][0][pipeline_id]' => $pipelineId,
                'filter[statuses][0][status_id]' => $wonStatusId,
            ],
        ]);

        return Arr::get($response->json(), '_embedded.leads', []);
    }

    public function salaryPipelineIds(): array
    {
        return array_keys($this->salaryPipelines());
    }

    private function salaryPipelines(): array
    {
        $pipelines = (array) config('services.amocrm.salary_pipelines', []);
        $normalized = [];

        foreach ($pipelines as $id => $meta) {
            if (!is_numeric($id) || (int) $id <= 0) {
                continue;
            }

            $currency = strtoupper(trim((string) ($meta['currency'] ?? 'USD')));
            $normalized[(int) $id] = [
                'label' => (string) ($meta['label'] ?? ('Pipeline '.$id)),
                'type' => (string) ($meta['type'] ?? 'pipeline'),
                'currency' => in_array($currency, ['UAH', 'USD', 'EUR'], true) ? $currency : 'USD',
            ];
        }

        return $normalized;
    }

    private function salaryPipelineCurrency(int $pipelineId): string
    {
        return $this->salaryPipelines()[$pipelineId]['currency'] ?? 'USD';
    }

    private function financeStageIds(): array
    {
        $ids = array_filter(
            (array) config('services.amocrm.finance_stage_ids', []),
            fn ($id) => is_numeric($id) && (int) $id > 0
        );

        return array_values(array_unique(array_map('intval', $ids)));
    }

    private function ntvReportStageIds(): array
    {
        $ids = array_filter(
            (array) config('services.amocrm.ntv_report_stage_ids', []),
            fn ($id) => is_numeric($id) && (int) $id > 0
        );

        return array_values(array_unique(array_map('intval', $ids)));
    }

    public function getLeadById(int $leadId): ?array
    {
        $response = $this->apiRequest('GET', '/leads/'.$leadId, [
            'query' => [
                'with' => 'contacts,users',
            ],
        ]);

        if (!$response->successful()) {
            return null;
        }

        return $response->json();
    }

    private ?array $amoUsersCache = null;

    private function getAmoUsersMap(): array
    {
        if ($this->amoUsersCache !== null) {
            return $this->amoUsersCache;
        }

        $response = $this->apiRequest('GET', '/users', ['query' => ['limit' => 250]]);
        $users = Arr::get($response->json(), '_embedded.users', []);
        $this->amoUsersCache = collect($users)->keyBy('id')->all();

        return $this->amoUsersCache;
    }

    private function getAmoUserName(int $userId): string
    {
        if ($userId <= 0) return '';
        $map = $this->getAmoUsersMap();
        return trim((string) ($map[$userId]['name'] ?? ''));
    }

    public function getUserById(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        $response = $this->apiRequest('GET', '/users/'.$userId);

        if (!$response->successful()) {
            return null;
        }

        return $response->json();
    }

    public function getContactById(int $contactId): ?array
    {
        if ($contactId <= 0) {
            return null;
        }

        $response = $this->apiRequest('GET', '/contacts/'.$contactId);

        if (!$response->successful()) {
            return null;
        }

        return $response->json();
    }

    public function processWebhookPayload(array $payload): array
    {
        $events = $this->extractWebhookEvents($payload);
        $processed = 0;
        $failed = 0;

        foreach ($events as $event) {
            try {
                $this->handleWebhookEvent($event['type'], $event['lead']);
                $processed++;
            } catch (\Throwable $e) {
                $failed++;
                Log::error('amoCRM webhook event processing failed', [
                    'event_type' => $event['type'] ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'total' => count($events),
            'processed' => $processed,
            'failed' => $failed,
        ];
    }

    public function handleWebhookEvent(string $eventType, array $leadPayload): ?SalesProject
    {
        $leadId = (int) ($leadPayload['id'] ?? 0);

        if ($leadId <= 0) {
            Log::warning('amoCRM webhook lead without id', ['event_type' => $eventType]);
            return null;
        }

        $lead = $leadPayload;

        // Webhooks may send partial entity payload, fetch full lead when key fields are missing.
        if (!array_key_exists('name', $lead) || !array_key_exists('price', $lead)) {
            $full = $this->getLeadById($leadId);
            if ($full) {
                $lead = $full;
            }
        }

        $project = $this->syncLead($lead);

        if (!$project) {
            return null;
        }

        return $project;
    }

    public function syncDeals(array $deals): array
    {
        $created = 0;
        $updated = 0;
        $syncedIds = [];

        foreach ($deals as $deal) {
            $amoDealId = (int) ($deal['id'] ?? 0);
            $existingMap = AmoCrmDealMap::query()
                ->where('amo_deal_id', $amoDealId)
                ->exists();

            $project = $this->syncLead((array) $deal);
            if (!$project) {
                continue;
            }

            if ($existingMap) {
                $updated++;
            } else {
                $created++;
            }
            $syncedIds[] = $amoDealId;
        }

        return [
            'total' => count($deals),
            'created' => $created,
            'updated' => $updated,
            'synced_ids' => $syncedIds,
        ];
    }

    /**
     * Re-sync tracked deals (amocrm_deal_map) that moved out of project_status_ids.
     * Fetches all deals modified in the last 7 days from amoCRM (any stage) and
     * updates those already tracked in our amocrm_deal_map table.
     */
    public function syncOutOfStageProjectDeals(array $alreadySyncedIds): array
    {
        // Find deals we track that were NOT returned by the main sync (they may have moved to non-tracked stages)
        $missedDealIds = AmoCrmDealMap::query()
            ->join('sales_projects', 'sales_projects.id', '=', 'amocrm_deal_map.wallet_project_id')
            ->where('sales_projects.status', 'active')
            ->whereNotNull('amocrm_deal_map.wallet_project_id')
            ->whereNotIn('amocrm_deal_map.amo_deal_id', $alreadySyncedIds)
            ->pluck('amocrm_deal_map.amo_deal_id')
            ->all();

        $updated = 0;

        foreach ($missedDealIds as $amoDealId) {
            $fullLead = $this->getLeadById((int) $amoDealId);

            if (!is_array($fullLead) || empty($fullLead)) {
                // Deal is gone from AMO — cancel the project
                $map = AmoCrmDealMap::query()->where('amo_deal_id', $amoDealId)->first();
                if ($map && $map->wallet_project_id) {
                    SalesProject::query()
                        ->where('id', $map->wallet_project_id)
                        ->whereNotIn('status', ['completed', 'cancelled'])
                        ->update(['status' => 'cancelled']);
                    $map->delete();
                }
                $updated++;
                continue;
            }

            $project = $this->syncLead($fullLead);
            if ($project) {
                $updated++;
            }
        }

        return ['updated' => $updated];
    }

    public function syncComplectationDeals(array $deals, bool $allowWonStatus = false): array
    {
        $financeStageIds = $this->financeStageIds();

        // Include finance stage IDs from salary pipelines (e.g. retail pipeline stages)
        foreach ($this->salaryPipelines() as $meta) {
            foreach ((array) ($meta['finance_stage_ids'] ?? []) as $id) {
                if ((int) $id > 0) {
                    $financeStageIds[] = (int) $id;
                }
            }
        }
        $financeStageIds = array_values(array_unique($financeStageIds));

        foreach ($this->ntvReportStageIds() as $id) {
            if ((int) $id > 0) {
                $financeStageIds[] = (int) $id;
            }
        }
        $financeStageIds = array_values(array_unique($financeStageIds));

        $wonStatusId = (int) config('services.amocrm.won_status_id', 142);
        $created = 0;
        $updated = 0;
        $syncedIds = [];

        foreach ($deals as $deal) {
            $lead = (array) $deal;
            $amoDealId = (int) ($lead['id'] ?? 0);
            if ($amoDealId <= 0) {
                continue;
            }

            // Use full lead payload to ensure custom_fields_values are available.
            $fullLead = $this->getLeadById($amoDealId);
            if (!is_array($fullLead) || empty($fullLead) || !empty($fullLead['is_deleted'])) {
                AmoComplectationProject::where('amo_deal_id', $amoDealId)->delete();
                continue;
            }
            $lead = $fullLead;

            $dealStatusId = (int) ($lead['status_id'] ?? 0);
            if (!in_array($dealStatusId, $financeStageIds, true) && !($allowWonStatus && $dealStatusId === $wonStatusId)) {
                continue;
            }

            $result = $this->upsertDeal($lead);
            $created += $result['created'];
            $updated += $result['updated'];
            $syncedIds[] = $amoDealId;
        }

        return [
            'total' => count($deals),
            'created' => $created,
            'updated' => $updated,
            'synced_ids' => $syncedIds,
        ];
    }

    public function syncSalaryWonDeals(array $deals, int $pipelineId): array
    {
        $wonStatusId = (int) config('services.amocrm.won_status_id', 142);
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $syncedIds = [];

        foreach ($deals as $deal) {
            $amoDealId = (int) ($deal['id'] ?? 0);
            if ($amoDealId <= 0) {
                $skipped++;
                continue;
            }

            $lead = $this->getLeadById($amoDealId);
            if (!is_array($lead) || empty($lead) || !empty($lead['is_deleted'])) {
                AmoComplectationProject::where('amo_deal_id', $amoDealId)->delete();
                $skipped++;
                continue;
            }

            $dealStatusId = (int) ($lead['status_id'] ?? 0);
            if ($dealStatusId !== $wonStatusId) {
                $skipped++;
                continue;
            }

            $row = AmoComplectationProject::query()->where('amo_deal_id', $amoDealId)->first();
            $responsibleUserId = (int) ($lead['responsible_user_id'] ?? 0);
            $responsibleName = $this->getAmoUserName($responsibleUserId);
            $totalAmount = (float) ($lead['price'] ?? 0);
            if ($totalAmount <= 0) {
                $totalAmount = 0.01;
            }
            $leadPipelineId = (int) ($lead['pipeline_id'] ?? $pipelineId);

            $payload = [
                'client_name' => mb_substr($this->extractProjectClientName($lead) ?: 'amoCRM deal #'.$amoDealId, 0, 255),
                'deal_name' => mb_substr(trim((string) ($lead['name'] ?? '')), 0, 255),
                'total_amount' => round($totalAmount, 2),
                'pipeline_id' => $leadPipelineId > 0 ? $leadPipelineId : null,
                'currency' => $this->extractCurrency($lead, $this->salaryPipelineCurrency($leadPipelineId)),
                'responsible_user_id' => $responsibleUserId ?: null,
                'responsible_name' => $responsibleName !== '' ? mb_substr($responsibleName, 0, 255) : null,
                'status_id' => $dealStatusId,
                'raw_payload' => $lead,
            ];

            $amoClosedAt = $this->amoClosedAtFromLead($lead);
            if ($amoClosedAt !== null) {
                $payload['amo_closed_at'] = $amoClosedAt;
            }

            if (!$row || (int) ($row->status_id ?? 0) !== $wonStatusId || empty($row->won_at)) {
                $payload['won_at'] = now()->toDateTimeString();
            }

            AmoComplectationProject::query()->updateOrCreate(
                ['amo_deal_id' => $amoDealId],
                $payload
            );

            $row ? $updated++ : $created++;
            $syncedIds[] = $amoDealId;
        }

        Log::info('[Salary Sync]', [
            'pipeline' => $pipelineId,
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
        ]);

        return [
            'total' => count($deals),
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'synced_ids' => $syncedIds,
        ];
    }

    /**
     * Re-sync tracked deals that are no longer in the complectation stages.
     *
     * Two passes:
     * 1. Recent changes: fetch all deals modified in the last 2 hours from amoCRM
     *    (any stage) and update those already tracked in our table.
     * 2. Stale backfill: process a small batch of tracked deals that haven't been
     *    re-synced recently, ensuring all deals are eventually refreshed.
     */
    public function syncOutOfStageDeals(array $alreadySyncedIds, int $staleBatchSize = 30): array
    {
        $trackedDealIds = AmoComplectationProject::query()
            ->whereNotNull('wallet_project_id')
            ->pluck('amo_deal_id')
            ->flip();

        $updated = 0;
        $recentlySyncedIds = $alreadySyncedIds;

        // Pass 1: deals modified in amoCRM in the last 2 hours (any stage).
        $since = Carbon::now()->subHours(2)->timestamp;
        $page = 1;

        do {
            $query = [
                'page' => $page,
                'limit' => 250,
                'with' => 'contacts,users',
                'filter[updated_at][from]' => $since,
            ];

            $response = $this->apiRequest('GET', '/leads', ['query' => $query]);
            $leads = Arr::get($response->json(), '_embedded.leads', []);

            if (empty($leads)) {
                break;
            }

            foreach ($leads as $deal) {
                $amoDealId = (int) ($deal['id'] ?? 0);

                if (!$trackedDealIds->has($amoDealId) || in_array($amoDealId, $alreadySyncedIds, true)) {
                    continue;
                }

                $fullLead = $this->getLeadById($amoDealId);
                if (!is_array($fullLead) || empty($fullLead) || !empty($fullLead['is_deleted'])) {
                    AmoComplectationProject::where('amo_deal_id', $amoDealId)->delete();
                    $recentlySyncedIds[] = $amoDealId;
                    continue;
                }

                $result = $this->upsertDeal($fullLead, onlyUpdate: true);
                $updated += $result['updated'];
                $recentlySyncedIds[] = $amoDealId;
            }

            $page++;
        } while (count($leads) === 250);

        // Pass 2: stale backfill — oldest un-synced deals, a small batch per run.
        $cutoff = Carbon::now()->subHours(2);

        $staleRows = AmoComplectationProject::query()
            ->whereNotIn('amo_deal_id', $recentlySyncedIds)
            ->where('updated_at', '<', $cutoff)
            ->whereNotNull('wallet_project_id')
            ->orderBy('updated_at')
            ->limit($staleBatchSize)
            ->get();

        $processedIds = [];

        foreach ($staleRows as $row) {
            $dealId = (int) $row->amo_deal_id;
            $lead = $this->getLeadById($dealId);
            if (!is_array($lead) || empty($lead) || !empty($lead['is_deleted'])) {
                AmoComplectationProject::where('amo_deal_id', $dealId)->delete();
                continue;
            }

            $result = $this->upsertDeal($lead, onlyUpdate: true);
            $updated += $result['updated'];
            $processedIds[] = $dealId;
        }

        // Always bump updated_at on processed rows so they cycle to the back of the queue,
        // even when amoCRM data was unchanged and Eloquent skipped the SQL update.
        if (!empty($processedIds)) {
            AmoComplectationProject::query()
                ->whereIn('amo_deal_id', $processedIds)
                ->update(['updated_at' => Carbon::now()]);
        }

        return ['updated' => $updated];
    }

    private function upsertDeal(array $lead, bool $onlyUpdate = false): array
    {
        $amoDealId = (int) ($lead['id'] ?? 0);
        $created = 0;
        $updated = 0;
        $budgetNotification = null;

        $clientName = $this->extractProjectClientName($lead);
        if ($clientName === '') {
            $clientName = 'amoCRM deal #'.$amoDealId;
        }

        $totalAmount = (float) ($lead['price'] ?? 0);
        if ($totalAmount <= 0) {
            $totalAmount = 0.01;
        }
        $projectPayload = $this->extractComplectationProjectFields($lead);

        $responsibleUserId = (int) ($lead['responsible_user_id'] ?? 0);
        $responsibleName = $this->getAmoUserName($responsibleUserId);

        $dealStatusId = (int) ($lead['status_id'] ?? 0);
        $identityPayload = $this->salesProjectAmoIdentityPayload($lead);

        DB::transaction(function () use ($amoDealId, $clientName, $lead, $totalAmount, $projectPayload, $identityPayload, $responsibleUserId, $responsibleName, $dealStatusId, $onlyUpdate, &$created, &$updated, &$budgetNotification) {
            $row = AmoComplectationProject::query()
                ->where('amo_deal_id', $amoDealId)
                ->lockForUpdate()
                ->first();

            $project = null;
            $walletProjectId = (int) ($row->wallet_project_id ?? 0);
            if ($walletProjectId > 0) {
                $project = SalesProject::query()
                    ->where('id', $walletProjectId)
                    ->whereIn('source_layer', ['finance', 'projects'])
                    ->orWhere(fn ($q) => $q->where('id', $walletProjectId)->whereNull('source_layer'))
                    ->first();
            }

            if (!$project) {
                if ($onlyUpdate) {
                    return;
                }
                $currency = $this->extractCurrency($lead, 'USD');
                $managerFields = $responsibleUserId > 0 ? ['lead_manager_user_id' => $responsibleUserId] : [];
                $project = SalesProject::query()->create(array_merge([
                    'client_name' => mb_substr($clientName, 0, 255),
                    'total_amount' => round($totalAmount, 2),
                    'remaining_amount' => round($totalAmount, 2),
                    'currency' => $currency,
                    'created_by' => $this->systemUserId(),
                    'source_layer' => 'finance',
                ], $identityPayload, $managerFields, $projectPayload));

                $created++;
            } else {
                $oldAmount = round((float) $project->total_amount, 2);
                $newAmount = round($totalAmount, 2);

                $managerFields = $responsibleUserId > 0 ? ['lead_manager_user_id' => $responsibleUserId] : [];
                $project->update(array_merge([
                    'client_name' => mb_substr($clientName, 0, 255),
                    'total_amount' => $newAmount,
                ], $identityPayload, $managerFields, $this->applyAppWinsFilter($projectPayload, $project)));

                if ($oldAmount > 0.01 && abs($newAmount - $oldAmount) >= 0.01) {
                    $budgetNotification = [
                        'client'     => mb_substr($clientName, 0, 80),
                        'old'        => $oldAmount,
                        'new'        => $newAmount,
                        'currency'   => $project->currency ?? 'USD',
                        'project_id' => $project->id,
                    ];
                }

                $updated++;
            }

            $wonStatusId = (int) config('services.amocrm.won_status_id', 142);
            $amoClosedAt = $this->amoClosedAtFromLead($lead);
            $pipelineId = (int) ($lead['pipeline_id'] ?? 0);
            $currency = $this->extractCurrency($lead, $this->salaryPipelineCurrency($pipelineId));
            $amoPayload = [
                'wallet_project_id' => $project->id,
                'client_name' => mb_substr($clientName, 0, 255),
                'deal_name' => mb_substr(trim((string) ($lead['name'] ?? '')), 0, 255),
                'total_amount' => round($totalAmount, 2),
                'pipeline_id' => $pipelineId > 0 ? $pipelineId : null,
                'currency' => $currency,
                'responsible_user_id' => $responsibleUserId ?: null,
                'responsible_name' => $responsibleName !== '' ? mb_substr($responsibleName, 0, 255) : null,
                'status_id' => $dealStatusId,
                'raw_payload' => $lead,
            ];

            if ($amoClosedAt !== null) {
                $amoPayload['amo_closed_at'] = $amoClosedAt;
            }

            // Record timestamp when deal first moves to Won (grace period for finance view)
            if ($dealStatusId === $wonStatusId) {
                $prevStatusId = (int) ($row?->status_id ?? 0);
                if ($prevStatusId !== $wonStatusId || empty($row?->won_at)) {
                    $amoPayload['won_at'] = now()->toDateTimeString();
                }
            }

            if ($row) {
                $row->update($amoPayload);
            } else {
                AmoComplectationProject::query()->create(array_merge([
                    'amo_deal_id' => $amoDealId,
                ], $amoPayload));
            }
        }, 3);

        if ($budgetNotification) {
            $diff     = round($budgetNotification['new'] - $budgetNotification['old'], 2);
            $sign     = $diff > 0 ? '+' : '';
            $currency = $budgetNotification['currency'];
            $message  = "{$budgetNotification['client']}: бюджет {$budgetNotification['old']} → {$budgetNotification['new']} {$currency} ({$sign}{$diff})";

            try {
                app(NotificationService::class)->sendToRole('owner', '💰 Бюджет змінено в АМО', $message, 'system', [
                    'project_id' => (string) $budgetNotification['project_id'],
                ]);
            } catch (\Throwable $e) {
                Log::warning('upsertDeal: budget notification failed', ['error' => $e->getMessage()]);
            }
        }

        return ['created' => $created, 'updated' => $updated];
    }

    private function amoClosedAtFromLead(array $lead): ?string
    {
        $closedAt = $lead['closed_at'] ?? null;

        if (!is_numeric($closedAt) || (int) $closedAt <= 0) {
            return null;
        }

        return Carbon::createFromTimestamp((int) $closedAt)->toDateTimeString();
    }

    private function salesProjectAmoIdentityPayload(array $lead): array
    {
        $payload = [];

        if ($this->hasSalesProjectColumn('amo_deal_id')) {
            $amoDealId = (int) ($lead['id'] ?? 0);
            $payload['amo_deal_id'] = $amoDealId > 0 ? $amoDealId : null;
        }

        if ($this->hasSalesProjectColumn('pipeline_id')) {
            $pipelineId = (int) ($lead['pipeline_id'] ?? 0);
            $payload['pipeline_id'] = $pipelineId > 0 ? $pipelineId : null;
        }

        if ($this->hasSalesProjectColumn('amo_deal_name')) {
            $name = trim((string) ($lead['name'] ?? ''));
            $payload['amo_deal_name'] = $name !== '' ? mb_substr($name, 0, 255) : null;
        }

        if ($this->hasSalesProjectColumn('amo_status_id')) {
            $statusId = (int) ($lead['status_id'] ?? 0);
            $payload['amo_status_id'] = $statusId > 0 ? $statusId : null;
        }

        return $payload;
    }

    private function extractComplectationProjectFields(array $lead): array
    {
        $payload = [];

        $inverter = $this->extractLeadFieldValue($lead, ['Інвертор', 'Инвертор', 'Inverter'], [1202241]);
        $bms = $this->extractLeadFieldValue($lead, ['BMS', 'БМС']);
        $battery = $this->extractLeadFieldValue($lead, ['АКБ', 'Акумулятор', 'Батарея', 'Battery'], [1200259]);
        $panels = $this->extractLeadFieldValue($lead, ['Панелі', 'Панели', 'ФЕМ', 'Сонячні панелі'], [1200253]);
        $mountingSystem = $this->extractLeadFieldAllValues($lead, [], [1204253]);
        // "Мета встановлення" (field_id=1208547): значення "ЗТ", "ЗТ + резерв", "Зелений тариф" тощо
        $metaRaw = $this->extractLeadFieldValue($lead, ['Мета встановлення', 'Зелений тариф', 'Зеленый тариф', 'Green tariff'], [1208547]);
        $phone = $this->extractLeadPhone($lead);

        if ($this->hasSalesProjectColumn('phone_number') && $phone !== null) {
            $payload['phone_number'] = mb_substr($phone, 0, 50);
        }
        if ($this->hasSalesProjectColumn('inverter') && $inverter !== null) {
            $canonical = \App\Services\InverterNormalizerService::normalize($inverter);
            $payload['inverter'] = mb_substr($canonical ?? $inverter, 0, 255);
        }
        if ($this->hasSalesProjectColumn('bms') && $bms !== null) {
            $payload['bms'] = mb_substr($bms, 0, 255);
        }
        if ($this->hasSalesProjectColumn('battery_name') && $battery !== null) {
            $payload['battery_name'] = mb_substr($battery, 0, 255);
        }
        if ($this->hasSalesProjectColumn('battery_qty') && $battery !== null) {
            $batteryQty = $this->extractQuantity($battery);
            if ($batteryQty !== null) {
                $payload['battery_qty'] = $batteryQty;
            }
        }
        if ($this->hasSalesProjectColumn('panel_name') && $panels !== null) {
            $payload['panel_name'] = mb_substr($panels, 0, 255);
        }
        if ($this->hasSalesProjectColumn('mounting_system') && $mountingSystem !== null) {
            $payload['mounting_system'] = mb_substr($mountingSystem, 0, 500);
        }
        if ($this->hasSalesProjectColumn('panel_qty') && $panels !== null) {
            $panelQty = $this->extractQuantity($panels);
            if ($panelQty !== null) {
                $payload['panel_qty'] = $panelQty;
            }
        }

        // "Доставлено на об'єкт" boolean flags:
        // field_id 1208519 = Інветорне обладнання (Так/Ні)
        // field_id 1208513 = Сонячні панелі (Так/Ні)
        $deliveredInverterRaw = $this->extractLeadFieldValue($lead, ['Інветорне обладнання', 'Інверторне обладнання'], [1208519]);
        if ($this->hasSalesProjectColumn('delivered_inverter') && $deliveredInverterRaw !== null) {
            $payload['delivered_inverter'] = mb_strtolower(trim($deliveredInverterRaw)) === 'так' ? 1 : 0;
        }

        $deliveredPanelsRaw = $this->extractLeadFieldValue($lead, ['Сонячні панелі'], [1208513]);
        if ($this->hasSalesProjectColumn('delivered_panels') && $deliveredPanelsRaw !== null) {
            $payload['delivered_panels'] = mb_strtolower(trim($deliveredPanelsRaw)) === 'так' ? 1 : 0;
        }
        if ($this->hasSalesProjectColumn('has_green_tariff') && $metaRaw !== null) {
            $metaLower = mb_strtolower(trim($metaRaw));
            $payload['has_green_tariff'] = str_contains($metaLower, 'зт')
                || str_contains($metaLower, 'зелений')
                || str_contains($metaLower, 'зеленый')
                || str_contains($metaLower, 'green tariff');
        }

        // field_id 107031 = "Предоплата, $" — the real advance field in this AMO pipeline
        $advanceRaw = $this->extractLeadFieldValue($lead, ['Предоплата, $', 'Предоплата', 'Аванс', 'Завдаток', 'Аванс/Завдаток', 'advance', 'Advance', 'First payment', 'Перший платіж'], [107031]);
        if ($this->hasSalesProjectColumn('advance_amount') && $advanceRaw !== null) {
            $advanceNum = (float) preg_replace('/[^\d.]/', '', (string) $advanceRaw);
            if ($advanceNum > 0) {
                $payload['advance_amount'] = round($advanceNum, 2);
            }
        }

        // field_id 107033 = "Остаток, $" — remaining balance owed by client
        $remainingRaw = $this->extractLeadFieldValue($lead, ['Остаток, $', 'Остаток', 'Залишок', 'Remaining', 'remaining'], [107033]);
        if ($this->hasSalesProjectColumn('remaining_amount') && $remainingRaw !== null) {
            $remainingNum = (float) preg_replace('/[^\d.]/', '', (string) $remainingRaw);
            if ($remainingNum >= 0) {
                $payload['remaining_amount'] = round($remainingNum, 2);
            }
        }

        $telegram = $this->extractLeadFieldValue($lead, ['Telegram', 'Телеграм', 'TG', 'Telegram чат', 'Telegram group'], [1216000]);
        if ($this->hasSalesProjectColumn('telegram_group_link') && $telegram !== null) {
            $payload['telegram_group_link'] = mb_substr($telegram, 0, 512);
        }

        return $payload;
    }

    /**
     * Remove delivered_* fields from a tech-fields payload when the project already has
     * those fields filled in. "App wins" rule: foreman data takes priority over AmoCRM.
     */
    private function applyAppWinsFilter(array $techFields, SalesProject $project): array
    {
        // delivered_inverter and delivered_panels are boolean flags (0/1) synced from AmoCRM —
        // never block them here; they always come from AmoCRM source of truth.
        return $techFields;
    }

    public function extractWebhookEvents(array $payload): array
    {
        $events = [];

        foreach ((array) Arr::get($payload, 'leads.add', []) as $lead) {
            $events[] = ['type' => self::EVENT_LEAD_ADD, 'lead' => (array) $lead];
        }

        foreach ((array) Arr::get($payload, 'leads.update', []) as $lead) {
            $events[] = ['type' => self::EVENT_LEAD_UPDATE, 'lead' => (array) $lead];
        }

        foreach ((array) Arr::get($payload, 'leads.status', []) as $lead) {
            $type = $this->isWonStatus((array) $lead) ? self::EVENT_LEAD_WON : self::EVENT_LEAD_UPDATE;
            $events[] = ['type' => $type, 'lead' => (array) $lead];
        }

        if (empty($events) && !empty($payload['id'])) {
            $lead = (array) $payload;
            $type = $this->isWonStatus($lead) ? self::EVENT_LEAD_WON : self::EVENT_LEAD_UPDATE;
            $events[] = ['type' => $type, 'lead' => $lead];
        }

        return $events;
    }

    /**
     * Check all tracked amoCRM deals and cancel projects whose deals no longer
     * exist in amoCRM (deleted or permanently lost).
     *
     * Strategy: batch-fetch deals by ID from amoCRM API (50 per request).
     * Any deal ID that amoCRM doesn't return is considered deleted → cancel project.
     *
     * @return array{cancelled: int, checked: int}
     */
    public function cancelDeletedAmoDeals(): array
    {
        $activeProjectIds = DB::table('sales_projects')
            ->whereNotIn('status', ['cancelled', 'completed'])
            ->pluck('id')
            ->all();

        $rows = AmoCrmDealMap::query()
            ->whereNotNull('wallet_project_id')
            ->whereIn('wallet_project_id', $activeProjectIds)
            ->get(['amo_deal_id', 'wallet_project_id']);

        if ($rows->isEmpty()) {
            return ['checked' => 0, 'cancelled' => 0];
        }

        $cancelled = 0;
        $checked   = 0;
        $systemId  = $this->systemUserId();
        $now       = Carbon::now();

        foreach ($rows->chunk(50) as $batch) {
            $batchIds = $batch->pluck('amo_deal_id')->map(fn ($id) => (int) $id)->all();

            $query = ['limit' => 250, 'with' => 'contacts'];
            foreach (array_values($batchIds) as $i => $id) {
                $query['filter[id][' . $i . ']'] = $id;
            }

            $response  = $this->apiRequest('GET', '/leads', ['query' => $query]);
            $returned  = collect(Arr::get($response->json(), '_embedded.leads', []))
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->flip();

            foreach ($batch as $row) {
                $amoDealId = (int) $row->amo_deal_id;
                $checked++;

                if ($returned->has($amoDealId)) {
                    continue;
                }

                // Deal not returned by amoCRM → treat as deleted.
                $affected = DB::table('sales_projects')
                    ->where('id', $row->wallet_project_id)
                    ->whereNotIn('status', ['cancelled', 'completed'])
                    ->update([
                        'status'             => 'cancelled',
                        'cancelled_at'       => $now,
                        'cancelled_by'       => $systemId,
                        'cancelled_by_actor' => 'system',
                    ]);

                if ($affected > 0) {
                    $cancelled++;
                    Log::info('amocrm: deal deleted in AMO, project cancelled', [
                        'amo_deal_id'        => $amoDealId,
                        'wallet_project_id'  => $row->wallet_project_id,
                    ]);
                }
            }

            // Respect amoCRM rate limits (7 req/s), sleep between batches.
            usleep(200_000);
        }

        return ['checked' => $checked, 'cancelled' => $cancelled];
    }

    private function syncLead(array $lead): ?SalesProject
    {
        $amoDealId = (int) ($lead['id'] ?? 0);
        if ($amoDealId <= 0) {
            return null;
        }

        $clientName = $this->extractProjectClientName($lead);
        if ($clientName === '') {
            $clientName = 'amoCRM deal #'.$amoDealId;
        }

        $responsibleUserId = (int) ($lead['responsible_user_id'] ?? 0);
        $responsibleName = $this->getAmoUserName($responsibleUserId);
        $identityPayload = $this->salesProjectAmoIdentityPayload($lead);

        return DB::transaction(function () use ($amoDealId, $lead, $clientName, $responsibleName, $identityPayload) {
            $map = AmoCrmDealMap::query()
                ->where('amo_deal_id', $amoDealId)
                ->lockForUpdate()
                ->first();

            $project = $map
                ? SalesProject::query()->find($map->wallet_project_id)
                : null;

            $totalAmount = (float) ($lead['price'] ?? 0);
            if ($totalAmount <= 0) {
                $totalAmount = $project ? (float) $project->total_amount : 0.01;
            }

            $projectStatusIds = config('services.amocrm.project_status_ids', []);
            $leadStatusId = (int) ($lead['status_id'] ?? 0);
            $isProjectCreateStage = $leadStatusId > 0 && in_array($leadStatusId, $projectStatusIds, true);

            if (!$project && !$isProjectCreateStage) {
                return null;
            }

            $techFields = $this->extractComplectationProjectFields($lead);

            if (!$project) {
                $currency = $this->extractCurrency($lead, 'USD');

                $project = SalesProject::query()->create(array_merge([
                    'client_name' => mb_substr($clientName, 0, 255),
                    'total_amount' => round($totalAmount, 2),
                    'remaining_amount' => round($totalAmount, 2),
                    'currency' => $currency,
                    'created_by' => $this->systemUserId(),
                    'source_layer' => 'projects',
                ], $identityPayload, $techFields));

                // New amoCRM-created project must start with no advances/transfers.
                // This also protects against SQLite id reuse inheriting old transfers.
                DB::table('cash_transfers')
                    ->where('project_id', $project->id)
                    ->delete();

                $mapData = [
                    'wallet_project_id' => $project->id,
                    'amo_status_id' => $leadStatusId ?: null,
                    'responsible_name' => $responsibleName !== '' ? mb_substr($responsibleName, 0, 255) : null,
                ];

                if ($map) {
                    $map->update($mapData);
                } else {
                    AmoCrmDealMap::query()->create(array_merge([
                        'amo_deal_id' => $amoDealId,
                        'created_at' => now(),
                    ], $mapData));
                }

                return $project;
            }

            $wonStatusId  = (int) config('services.amocrm.won_status_id', 142);
            $lostStatusId = (int) config('services.amocrm.lost_status_id', 143);
            $isWon  = $leadStatusId > 0 && $leadStatusId === $wonStatusId;
            $isLost = $leadStatusId > 0 && $leadStatusId === $lostStatusId;

            // Once a row is project-layer it must never be downgraded to finance-layer by a stage change.
            $newSourceLayer = ($project->source_layer === 'projects')
                ? 'projects'
                : ($isProjectCreateStage ? 'projects' : 'finance');

            if ($project->source_layer === 'projects' && !$isProjectCreateStage) {
                Log::info('amocrm:syncLead:source_layer_preserved', [
                    'project_id'    => $project->id,
                    'amo_deal_id'   => $amoDealId,
                    'amo_status_id' => $leadStatusId,
                    'reason'        => 'deal moved out of project stages but source_layer kept as projects',
                ]);
            }

            $updatePayload = array_merge([
                'client_name' => mb_substr($clientName, 0, 255),
                'total_amount' => round($totalAmount, 2),
                'source_layer' => $newSourceLayer,
            ], $identityPayload, $this->applyAppWinsFilter($techFields, $project));

            // Re-activate project if it was cancelled/completed but deal moved back to an active stage
            if (!$isWon && !$isLost && in_array($project->status, ['completed', 'cancelled']) && $isProjectCreateStage) {
                $updatePayload['status'] = 'active';
            }

            $project->update($updatePayload);

            if (($isWon || $isLost) && $project->status !== 'completed') {
                $this->markProjectCompleted($project);
            }

            if ($map && $leadStatusId > 0) {
                $mapUpdate = ['amo_status_id' => $leadStatusId];
                if ($responsibleName !== '') {
                    $mapUpdate['responsible_name'] = mb_substr($responsibleName, 0, 255);
                }
                $map->update($mapUpdate);
            }

            return $project->fresh();
        }, 3);
    }

    private function markProjectCompleted(SalesProject $project): void
    {
        $payload = ['status' => 'completed'];

        if (Schema::hasColumn('sales_projects', 'closed_at')) {
            $payload['closed_at'] = now();
        }

        if (Schema::hasColumn('sales_projects', 'closed_by')) {
            $payload['closed_by'] = $this->systemUserId();
        }

        $project->update($payload);
    }

    private function extractCurrency(array $lead, ?string $fallback = null): string
    {
        $fallback = strtoupper(trim((string) $fallback));

        $candidate = strtoupper(trim((string) ($lead['currency'] ?? '')));
        if (in_array($candidate, ['UAH', 'USD', 'EUR'], true)) {
            return $candidate;
        }

        foreach ((array) ($lead['custom_fields_values'] ?? []) as $field) {
            $fieldName = mb_strtolower(trim((string) ($field['field_name'] ?? '')));
            $fieldCode = mb_strtolower(trim((string) ($field['field_code'] ?? '')));

            if (!in_array($fieldName, ['currency', 'валюта'], true) && $fieldCode !== 'currency') {
                continue;
            }

            foreach ((array) ($field['values'] ?? []) as $valueRow) {
                $v = strtoupper(trim((string) ($valueRow['value'] ?? '')));
                if (in_array($v, ['UAH', 'USD', 'EUR'], true)) {
                    return $v;
                }
            }
        }

        return in_array($fallback, ['UAH', 'USD', 'EUR'], true) ? $fallback : 'USD';
    }

    private function extractProjectClientName(array $lead): string
    {
        // 1) Prefer explicit custom fields when CRM stores customer/project title there.
        foreach ((array) ($lead['custom_fields_values'] ?? []) as $field) {
            $fieldName = mb_strtolower(trim((string) ($field['field_name'] ?? '')));
            $fieldCode = mb_strtolower(trim((string) ($field['field_code'] ?? '')));

            $isClientNameField = in_array($fieldName, [
                'контактна особа',
                'клієнт',
                'клиент',
                'замовник',
                'customer',
                'customer name',
                'client name',
                'project name',
                'назва проекту',
            ], true) || in_array($fieldCode, [
                'contact_name',
                'client_name',
                'customer_name',
                'project_name',
            ], true);

            if (!$isClientNameField) {
                continue;
            }

            foreach ((array) ($field['values'] ?? []) as $valueRow) {
                $value = trim((string) ($valueRow['value'] ?? ''));
                if ($value !== '') {
                    return $value;
                }
            }
        }

        // 2) Fallback to first linked contact name.
        $contactName = trim((string) (
            Arr::get($lead, '_embedded.contacts.0.name')
            ?? Arr::get($lead, '_embedded.contacts.0.first_name')
            ?? ''
        ));
        if ($contactName !== '') {
            return $contactName;
        }

        // 3) If embedded contact has only id, load full contact.
        $contactId = (int) (Arr::get($lead, '_embedded.contacts.0.id') ?? 0);
        if ($contactId > 0) {
            $contact = $this->getContactById($contactId);
            $contactName = trim((string) (
                $contact['name']
                ?? $contact['first_name']
                ?? ''
            ));
            if ($contactName !== '') {
                return $contactName;
            }
        }

        // 4) Fallback to deal title.
        return trim((string) ($lead['name'] ?? ''));
    }

    private function extractLeadFieldValue(array $lead, array $fieldNames, array $fieldIds = []): ?string
    {
        $normalized = collect($fieldNames)
            ->map(fn ($name) => mb_strtolower(trim((string) $name)))
            ->filter()
            ->values()
            ->all();
        $normalizedIds = collect($fieldIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();

        if ($normalized === [] && $normalizedIds === []) {
            return null;
        }

        foreach ((array) ($lead['custom_fields_values'] ?? []) as $field) {
            $currentId = (int) ($field['field_id'] ?? 0);
            $currentName = mb_strtolower(trim((string) ($field['field_name'] ?? '')));
            $matchesById = in_array($currentId, $normalizedIds, true);
            $matchesByName = in_array($currentName, $normalized, true);

            if (!$matchesById && !$matchesByName) {
                continue;
            }

            foreach ((array) ($field['values'] ?? []) as $valueRow) {
                $value = trim((string) ($valueRow['value'] ?? ''));
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }

    private function extractLeadFieldAllValues(array $lead, array $fieldNames, array $fieldIds = []): ?string
    {
        $normalized = collect($fieldNames)->map(fn ($n) => mb_strtolower(trim((string) $n)))->filter()->values()->all();
        $normalizedIds = collect($fieldIds)->map(fn ($id) => (int) $id)->filter(fn ($id) => $id > 0)->values()->all();

        foreach ((array) ($lead['custom_fields_values'] ?? []) as $field) {
            $currentId = (int) ($field['field_id'] ?? 0);
            $currentName = mb_strtolower(trim((string) ($field['field_name'] ?? '')));
            if (!in_array($currentId, $normalizedIds, true) && !in_array($currentName, $normalized, true)) {
                continue;
            }
            $values = array_filter(array_map(fn ($v) => trim((string) ($v['value'] ?? '')), (array) ($field['values'] ?? [])));
            return $values ? implode(', ', $values) : null;
        }

        return null;
    }

    private function extractLeadPhone(array $lead): ?string
    {
        $embeddedPhone = trim((string) Arr::get($lead, '_embedded.contacts.0.custom_fields_values.0.values.0.value'));
        if ($embeddedPhone !== '') {
            return $embeddedPhone;
        }

        $contactId = (int) (Arr::get($lead, '_embedded.contacts.0.id') ?? 0);
        if ($contactId <= 0) {
            return null;
        }

        $contact = $this->getContactById($contactId);
        if (!$contact) {
            return null;
        }

        foreach ((array) ($contact['custom_fields_values'] ?? []) as $field) {
            $code = mb_strtolower(trim((string) ($field['field_code'] ?? '')));
            $name = mb_strtolower(trim((string) ($field['field_name'] ?? '')));
            if ($code !== 'phone' && !in_array($name, ['телефон', 'phone'], true)) {
                continue;
            }

            foreach ((array) ($field['values'] ?? []) as $valueRow) {
                $value = trim((string) ($valueRow['value'] ?? ''));
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }

    private function extractQuantity(?string $raw): ?int
    {
        if (!$raw) {
            return null;
        }

        $value = mb_strtolower(trim($raw));

        // Typical amo formats: "5шт", "5 шт.", "5штук", "5 in", "5in".
        if (preg_match('/(\d{1,4})\s*(шт|штук|in)\.?\b/u', $value, $m) === 1) {
            $qty = (int) $m[1];
            return $qty > 0 ? $qty : null;
        }

        return null;
    }

    private function normalizeBooleanLikeValue(string $raw): bool
    {
        $value = mb_strtolower(trim($raw));
        return in_array($value, ['1', 'yes', 'true', 'так', 'є', 'y'], true);
    }

    private function hasSalesProjectColumn(string $column): bool
    {
        static $cache = [];

        if (!array_key_exists($column, $cache)) {
            $cache[$column] = Schema::hasColumn('sales_projects', $column);
        }

        return $cache[$column];
    }

    private function isWonStatus(array $lead): bool
    {
        $completedId = (int) config('services.amocrm.completed_status_id', 0);
        $statusId = (int) ($lead['status_id'] ?? 0);

        if ($completedId > 0 && $statusId === $completedId) {
            return true;
        }

        $statusName = mb_strtolower(trim((string) (
            Arr::get($lead, '_embedded.status.name')
            ?? Arr::get($lead, 'status.name')
            ?? Arr::get($lead, 'status')
            ?? ''
        )));

        if ($statusName === '') {
            return false;
        }

        $wonNames = ['won', 'closed won', 'успешно реализовано', 'успішно реалізовано'];

        return in_array($statusName, $wonNames, true);
    }

    private function apiRequest(string $method, string $path, array $options = []): Response
    {
        $response = Http::timeout(30)
            ->acceptJson()
            ->withToken($this->getAccessToken())
            ->send($method, $this->apiBaseUrl().$path, $options);

        if ($response->status() === 401) {
            $this->refreshAccessToken();

            $response = Http::timeout(30)
                ->acceptJson()
                ->withToken($this->getAccessToken())
                ->send($method, $this->apiBaseUrl().$path, $options);
        }

        if (!$response->successful()) {
            Log::warning('amoCRM API request failed', [
                'method' => $method,
                'path' => $path,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }

        return $response;
    }

    private function storeTokenPayload(array $payload): AmoCrmToken
    {
        $accessToken = (string) ($payload['access_token'] ?? '');
        $refreshToken = (string) ($payload['refresh_token'] ?? '');
        $expiresIn = (int) ($payload['expires_in'] ?? 0);

        if ($accessToken === '' || $refreshToken === '' || $expiresIn <= 0) {
            throw new \RuntimeException('Invalid amoCRM token payload.');
        }

        return AmoCrmToken::query()->create([
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_at' => now()->addSeconds($expiresIn),
        ]);
    }

    private function oauthTokenUrl(): string
    {
        return 'https://'.$this->domain().'/oauth2/access_token';
    }

    private function apiBaseUrl(): string
    {
        return 'https://'.$this->domain().'/api/v4';
    }

    private function domain(): string
    {
        $domain = trim((string) config('services.amocrm.domain'));
        $domain = preg_replace('#^https?://#i', '', $domain) ?? '';
        $domain = rtrim($domain, '/');

        if ($domain === '') {
            throw new \RuntimeException('AMO_DOMAIN is not configured.');
        }

        return $domain;
    }

    private function systemUserId(): int
    {
        static $id;

        if ($id) {
            return $id;
        }

        $id = (int) (User::query()->where('role', 'owner')->value('id')
            ?? User::query()->value('id')
            ?? 1);

        return $id;
    }

    private function bootstrapTokenFromConfig(): AmoCrmToken
    {
        $refreshToken = trim((string) config('services.amocrm.refresh_token', ''));
        $authCode = trim((string) config('services.amocrm.authorization_code', ''));

        if ($refreshToken !== '') {
            $tmp = AmoCrmToken::query()->create([
                'access_token' => 'bootstrap',
                'refresh_token' => $refreshToken,
                'expires_at' => now()->subMinute(),
            ]);

            try {
                return $this->refreshAccessToken();
            } finally {
                $tmp->delete();
            }
        }

        if ($authCode !== '') {
            $result = $this->exchangeAuthorizationCode($authCode);
            if (($result['ok'] ?? false) === true) {
                return AmoCrmToken::query()->latest('id')->firstOrFail();
            }

            throw new \RuntimeException(
                'amoCRM authorization_code exchange failed (HTTP '
                . ($result['status'] ?? '?')
                . '): '
                . ($result['body'] ?? 'unknown error')
                . ' — the code may be expired or already used.'
            );
        }

        throw new \RuntimeException('amoCRM token not found and AMO_* bootstrap credentials are missing.');
    }
}
