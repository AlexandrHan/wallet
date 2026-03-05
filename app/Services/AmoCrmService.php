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
        $importStatusId = (int) config('services.amocrm.won_status_id');

        $response = $this->apiRequest('GET', '/leads', [
            'query' => [
                'page' => $page,
                'limit' => $limit,
                'with' => 'contacts,users',
                'filter[statuses][0][status_id]' => $importStatusId,
            ],
        ]);

        $json = $response->json();

        return Arr::get($json, '_embedded.leads', []);
    }

    public function fetchComplectationDeals(int $page = 1, int $limit = 250): array
    {
        $statusId = (int) config('services.amocrm.complectation_status_id', 69586234);

        $response = $this->apiRequest('GET', '/leads', [
            'query' => [
                'page' => $page,
                'limit' => $limit,
                'with' => 'contacts,users',
                'filter[statuses][0][status_id]' => $statusId,
            ],
        ]);

        $json = $response->json();

        return Arr::get($json, '_embedded.leads', []);
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
        $importStatusId = (int) config('services.amocrm.won_status_id');

        foreach ($deals as $deal) {
            $statusId = (int) ($deal['status_id'] ?? 0);

            if ($statusId !== $importStatusId) {
                continue;
            }

            $existingMap = AmoCrmDealMap::query()
                ->where('amo_deal_id', (int) ($deal['id'] ?? 0))
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
        }

        return [
            'total' => count($deals),
            'created' => $created,
            'updated' => $updated,
        ];
    }

    public function syncComplectationDeals(array $deals): array
    {
        $statusId = (int) config('services.amocrm.complectation_status_id', 69586234);
        $created = 0;
        $updated = 0;

        foreach ($deals as $deal) {
            $lead = (array) $deal;
            if ((int) ($lead['status_id'] ?? 0) !== $statusId) {
                continue;
            }

            $amoDealId = (int) ($lead['id'] ?? 0);
            if ($amoDealId <= 0) {
                continue;
            }

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

            DB::transaction(function () use ($amoDealId, $clientName, $lead, $totalAmount, $projectPayload, &$created, &$updated) {
                $map = AmoCrmDealMap::query()
                    ->where('amo_deal_id', $amoDealId)
                    ->lockForUpdate()
                    ->first();

                $project = $map
                    ? SalesProject::query()->find($map->wallet_project_id)
                    : null;

                if (!$project) {
                    $currency = $this->extractCurrency($lead, 'USD');
                    $project = SalesProject::query()->create(array_merge([
                        'client_name' => mb_substr($clientName, 0, 255),
                        'total_amount' => round($totalAmount, 2),
                        'remaining_amount' => round($totalAmount, 2),
                        'currency' => $currency,
                        'created_by' => $this->systemUserId(),
                        'source_layer' => 'projects',
                    ], $projectPayload));

                    DB::table('cash_transfers')
                        ->where('project_id', $project->id)
                        ->delete();

                    if ($map) {
                        $map->update([
                            'wallet_project_id' => $project->id,
                        ]);
                    } else {
                        AmoCrmDealMap::query()->create([
                            'amo_deal_id' => $amoDealId,
                            'wallet_project_id' => $project->id,
                            'created_at' => now(),
                        ]);
                    }

                    $created++;
                } else {
                    $project->update(array_merge([
                        'client_name' => mb_substr($clientName, 0, 255),
                        'total_amount' => round($totalAmount, 2),
                    ], $projectPayload));
                    $updated++;
                }
            }, 3);

            $row = AmoComplectationProject::query()->where('amo_deal_id', $amoDealId)->first();
            $payload = [
                'amo_deal_id' => $amoDealId,
                'client_name' => mb_substr($clientName, 0, 255),
                'deal_name' => mb_substr(trim((string) ($lead['name'] ?? '')), 0, 255),
                'total_amount' => round($totalAmount, 2),
                'responsible_user_id' => $responsibleUserId ?: null,
                'responsible_name' => $responsibleName !== '' ? mb_substr($responsibleName, 0, 255) : null,
                'status_id' => $statusId,
                'raw_payload' => $lead,
            ];

            if ($row) {
                $row->update($payload);
            } else {
                AmoComplectationProject::query()->create($payload);
            }
        }

        return [
            'total' => count($deals),
            'created' => $created,
            'updated' => $updated,
        ];
    }

    private function extractComplectationProjectFields(array $lead): array
    {
        $payload = [];

        $inverter = $this->extractLeadFieldValue($lead, ['Інвертор', 'Инвертор', 'Inverter']);
        $bms = $this->extractLeadFieldValue($lead, ['BMS', 'БМС']);
        $battery = $this->extractLeadFieldValue($lead, ['АКБ', 'Акумулятор', 'Батарея', 'Battery']);
        $panels = $this->extractLeadFieldValue($lead, ['Панелі', 'Панели', 'ФЕМ', 'Сонячні панелі']);
        $hasGreenTariffRaw = $this->extractLeadFieldValue($lead, ['Зелений тариф', 'Зеленый тариф', 'Green tariff']);
        $phone = $this->extractLeadPhone($lead);

        if ($this->hasSalesProjectColumn('phone_number') && $phone !== null) {
            $payload['phone_number'] = mb_substr($phone, 0, 50);
        }
        if ($this->hasSalesProjectColumn('inverter') && $inverter !== null) {
            $payload['inverter'] = mb_substr($inverter, 0, 255);
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
        if ($this->hasSalesProjectColumn('has_green_tariff') && $hasGreenTariffRaw !== null) {
            $payload['has_green_tariff'] = $this->normalizeBooleanLikeValue($hasGreenTariffRaw);
        }
        return $payload;
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

            $projectCreateStatusId = (int) config('services.amocrm.won_status_id');
            $leadStatusId = (int) ($lead['status_id'] ?? 0);
            $isProjectCreateStage = $leadStatusId > 0 && $leadStatusId === $projectCreateStatusId;

            if (!$project && !$isProjectCreateStage) {
                return null;
            }

            if (!$project) {
                $currency = $this->extractCurrency($lead, 'USD');

                $project = SalesProject::query()->create([
                    'client_name' => mb_substr($clientName, 0, 255),
                    'total_amount' => round($totalAmount, 2),
                    'remaining_amount' => round($totalAmount, 2),
                    'currency' => $currency,
                    'created_by' => $this->systemUserId(),
                    'source_layer' => 'finance',
                ]);

                // New amoCRM-created project must start with no advances/transfers.
                // This also protects against SQLite id reuse inheriting old transfers.
                DB::table('cash_transfers')
                    ->where('project_id', $project->id)
                    ->delete();

                if ($map) {
                    $map->update([
                        'wallet_project_id' => $project->id,
                    ]);
                } else {
                    AmoCrmDealMap::query()->create([
                        'amo_deal_id' => $amoDealId,
                        'wallet_project_id' => $project->id,
                        'created_at' => now(),
                    ]);
                }

                return $project;
            }

            $project->update([
                'client_name' => mb_substr($clientName, 0, 255),
                'total_amount' => round($totalAmount, 2),
            ]);

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

    private function extractLeadFieldValue(array $lead, array $fieldNames): ?string
    {
        $normalized = collect($fieldNames)
            ->map(fn ($name) => mb_strtolower(trim((string) $name)))
            ->filter()
            ->values()
            ->all();

        if ($normalized === []) {
            return null;
        }

        foreach ((array) ($lead['custom_fields_values'] ?? []) as $field) {
            $currentName = mb_strtolower(trim((string) ($field['field_name'] ?? '')));
            if (!in_array($currentName, $normalized, true)) {
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

        if (preg_match('/\b(\d{1,4})\b/u', $raw, $m) !== 1) {
            return null;
        }

        $qty = (int) $m[1];
        return $qty > 0 ? $qty : null;
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
        }

        throw new \RuntimeException('amoCRM token not found and AMO_* bootstrap credentials are missing.');
    }
}
