<?php

namespace App\Services;

use App\Models\AmoCrmDealMap;
use App\Models\AmoComplectationProject;
use App\Models\AmoCrmToken;
use App\Models\SalesProject;
use App\Models\User;
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

        $query = [
            'page' => $page,
            'limit' => $limit,
            'with' => 'contacts,users',
        ];

        foreach (array_values($statusIds) as $i => $statusId) {
            $query['filter[statuses][' . $i . '][pipeline_id]'] = 4071382;
            $query['filter[statuses][' . $i . '][status_id]'] = $statusId;
        }

        $response = $this->apiRequest('GET', '/leads', ['query' => $query]);

        return Arr::get($response->json(), '_embedded.leads', []);
    }

    public function fetchComplectationDeals(int $page = 1, int $limit = 250): array
    {
        $financeStageIds = $this->financeStageIds();

        $query = [
            'page' => $page,
            'limit' => $limit,
            'with' => 'contacts,users',
        ];

        foreach (array_values($financeStageIds) as $i => $statusId) {
            $query['filter[statuses][' . $i . '][pipeline_id]'] = 4071382;
            $query['filter[statuses][' . $i . '][status_id]'] = $statusId;
        }

        $response = $this->apiRequest('GET', '/leads', ['query' => $query]);

        return Arr::get($response->json(), '_embedded.leads', []);
    }

    private function financeStageIds(): array
    {
        $ids = array_filter(
            (array) config('services.amocrm.finance_stage_ids', []),
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
        $trackedDealIds = AmoCrmDealMap::query()
            ->whereNotNull('wallet_project_id')
            ->pluck('amo_deal_id')
            ->flip();

        $updated = 0;
        $since = Carbon::now()->subDays(7)->timestamp;
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
                if (!is_array($fullLead) || empty($fullLead)) {
                    continue;
                }

                $project = $this->syncLead($fullLead);
                if ($project) {
                    $updated++;
                }
            }

            $page++;
        } while (count($leads) === 250);

        return ['updated' => $updated];
    }

    public function syncComplectationDeals(array $deals): array
    {
        $financeStageIds = $this->financeStageIds();
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
            if (is_array($fullLead) && !empty($fullLead)) {
                $lead = $fullLead;
            }

            $dealStatusId = (int) ($lead['status_id'] ?? 0);
            if (!in_array($dealStatusId, $financeStageIds, true)) {
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
                if (!is_array($fullLead) || empty($fullLead)) {
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
            $lead = $this->getLeadById((int) $row->amo_deal_id);
            if (!is_array($lead) || empty($lead)) {
                // Still mark as processed so it's not retried immediately.
                $processedIds[] = (int) $row->amo_deal_id;
                continue;
            }

            $result = $this->upsertDeal($lead, onlyUpdate: true);
            $updated += $result['updated'];
            $processedIds[] = (int) $row->amo_deal_id;
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
        $responsibleName = trim((string) (
            Arr::get($lead, '_embedded.users.0.name')
            ?? ''
        ));
        if ($responsibleName === '' && $responsibleUserId > 0) {
            $amoUser = $this->getUserById($responsibleUserId);
            $responsibleName = trim((string) ($amoUser['name'] ?? ''));
        }

        $dealStatusId = (int) ($lead['status_id'] ?? 0);

        DB::transaction(function () use ($amoDealId, $clientName, $lead, $totalAmount, $projectPayload, $responsibleUserId, $responsibleName, $dealStatusId, $onlyUpdate, &$created, &$updated) {
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
                ], $managerFields, $projectPayload));

                $created++;
            } else {
                $managerFields = $responsibleUserId > 0 ? ['lead_manager_user_id' => $responsibleUserId] : [];
                $project->update(array_merge([
                    'client_name' => mb_substr($clientName, 0, 255),
                    'total_amount' => round($totalAmount, 2),
                ], $managerFields, $this->applyAppWinsFilter($projectPayload, $project)));
                $updated++;
            }

            $amoPayload = [
                'wallet_project_id' => $project->id,
                'client_name' => mb_substr($clientName, 0, 255),
                'deal_name' => mb_substr(trim((string) ($lead['name'] ?? '')), 0, 255),
                'total_amount' => round($totalAmount, 2),
                'responsible_user_id' => $responsibleUserId ?: null,
                'responsible_name' => $responsibleName !== '' ? mb_substr($responsibleName, 0, 255) : null,
                'status_id' => $dealStatusId,
                'raw_payload' => $lead,
            ];

            if ($row) {
                $row->update($amoPayload);
            } else {
                AmoComplectationProject::query()->create(array_merge([
                    'amo_deal_id' => $amoDealId,
                ], $amoPayload));
            }
        }, 3);

        return ['created' => $created, 'updated' => $updated];
    }

    private function extractComplectationProjectFields(array $lead): array
    {
        $payload = [];

        $inverter = $this->extractLeadFieldValue($lead, ['Інвертор', 'Инвертор', 'Inverter'], [1202241]);
        $bms = $this->extractLeadFieldValue($lead, ['BMS', 'БМС']);
        $battery = $this->extractLeadFieldValue($lead, ['АКБ', 'Акумулятор', 'Батарея', 'Battery'], [1200259]);
        $panels = $this->extractLeadFieldValue($lead, ['Панелі', 'Панели', 'ФЕМ', 'Сонячні панелі'], [1200253]);
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

        return DB::transaction(function () use ($amoDealId, $lead, $clientName) {
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
                ], $techFields));

                // New amoCRM-created project must start with no advances/transfers.
                // This also protects against SQLite id reuse inheriting old transfers.
                DB::table('cash_transfers')
                    ->where('project_id', $project->id)
                    ->delete();

                if ($map) {
                    $map->update([
                        'wallet_project_id' => $project->id,
                        'amo_status_id' => $leadStatusId ?: null,
                    ]);
                } else {
                    AmoCrmDealMap::query()->create([
                        'amo_deal_id' => $amoDealId,
                        'wallet_project_id' => $project->id,
                        'amo_status_id' => $leadStatusId ?: null,
                        'created_at' => now(),
                    ]);
                }

                return $project;
            }

            $wonStatusId  = (int) config('services.amocrm.won_status_id', 142);
            $lostStatusId = (int) config('services.amocrm.lost_status_id', 143);
            $isWon  = $leadStatusId > 0 && $leadStatusId === $wonStatusId;
            $isLost = $leadStatusId > 0 && $leadStatusId === $lostStatusId;

            $project->update(array_merge([
                'client_name' => mb_substr($clientName, 0, 255),
                'total_amount' => round($totalAmount, 2),
                'source_layer' => $isProjectCreateStage ? 'projects' : 'finance',
            ], $this->applyAppWinsFilter($techFields, $project)));

            if (($isWon || $isLost) && $project->status !== 'completed') {
                $this->markProjectCompleted($project);
            }

            if ($map && $leadStatusId > 0) {
                $map->update(['amo_status_id' => $leadStatusId]);
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
