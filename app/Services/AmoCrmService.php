<?php

namespace App\Services;

use App\Models\AmoCrmDealMap;
use App\Models\AmoCrmToken;
use App\Models\CashTransfer;
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
        $wonStatusId = (int) config('services.amocrm.won_status_id');
        $projectStatusId = (int) config('services.amocrm.project_status_id', 69586234);

        $response = $this->apiRequest('GET', '/leads', [
            'query' => [
                'page' => $page,
                'limit' => $limit,
                'filter[statuses][0][status_id]' => $wonStatusId,
                'filter[statuses][1][status_id]' => $projectStatusId,
            ],
        ]);

        $json = $response->json();

        return Arr::get($json, '_embedded.leads', []);
    }

    public function getLeadById(int $leadId): ?array
    {
        $response = $this->apiRequest('GET', '/leads/'.$leadId);

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

        if ($eventType === self::EVENT_LEAD_WON || $this->isWonStatus($lead)) {
            $this->markProjectCompleted($project);
        }

        return $project;
    }

    public function syncDeals(array $deals): array
    {
        $created = 0;
        $updated = 0;
        $wonStatusId = (int) config('services.amocrm.won_status_id');
        $projectStatusId = (int) config('services.amocrm.project_status_id', 69586234);

        foreach ($deals as $deal) {
            $statusId = (int) ($deal['status_id'] ?? 0);

            if (!in_array($statusId, [$wonStatusId, $projectStatusId], true)) {
                continue;
            }

            $existingMap = AmoCrmDealMap::query()
                ->where('amo_deal_id', (int) ($deal['id'] ?? 0))
                ->exists();

            $project = $this->syncLead((array) $deal);
            if (!$project) {
                continue;
            }

            if ($this->isWonStatus((array) $deal)) {
                $this->markProjectCompleted($project);
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

        $clientName = trim((string) ($lead['name'] ?? ''));
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

            $currency = $this->extractCurrency($lead, $project?->currency);
            $partialPaidStatusId = (int) config('services.amocrm.won_status_id');
            $projectCreateStatusId = (int) config('services.amocrm.project_status_id', 69586234);
            $leadStatusId = (int) ($lead['status_id'] ?? 0);
            $isPartialPaidStage = $leadStatusId > 0 && $leadStatusId === $partialPaidStatusId;
            $isProjectCreateStage = $leadStatusId > 0 && $leadStatusId === $projectCreateStatusId;

            if (!$project) {
                $project = SalesProject::query()->create([
                    'client_name' => mb_substr($clientName, 0, 255),
                    'total_amount' => round($totalAmount, 2),
                    'advance_amount' => 0,
                    'remaining_amount' => round($totalAmount, 2),
                    'currency' => $currency,
                    'created_by' => $this->systemUserId(),
                    'status' => ($isPartialPaidStage || $isProjectCreateStage)
                        ? 'active'
                        : ($this->isWonStatus($lead) ? 'completed' : 'active'),
                ]);

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

            $acceptedPaid = $this->acceptedAdvanceSum($project->id);
            $remaining = max(round($totalAmount - $acceptedPaid, 2), 0);

            $project->update([
                'client_name' => mb_substr($clientName, 0, 255),
                'total_amount' => round($totalAmount, 2),
                'advance_amount' => round($acceptedPaid, 2),
                'remaining_amount' => $remaining,
                'currency' => $currency,
                'status' => ($isPartialPaidStage || $isProjectCreateStage)
                    ? 'active'
                    : ($this->isWonStatus($lead) ? 'completed' : ((string) $project->status ?: 'active')),
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

    private function syncAdvancePayment(SalesProject $project, float $advanceTargetTotal): void
    {
        $alreadyAccepted = $this->acceptedAdvanceSum($project->id);
        $delta = round($advanceTargetTotal - $alreadyAccepted, 2);

        if ($delta <= 0) {
            return;
        }

        CashTransfer::query()->create([
            'project_id' => $project->id,
            'from_wallet_id' => null,
            'to_wallet_id' => null,
            'amount' => $delta,
            'currency' => $project->currency,
            'exchange_rate' => null,
            // In this project this legacy field stores amount in project currency as well.
            'usd_amount' => $delta,
            'status' => 'accepted',
            'created_by' => $this->systemUserId(),
            'accepted_at' => now(),
        ]);

        $acceptedPaid = $alreadyAccepted + $delta;
        $project->update([
            'advance_amount' => round($acceptedPaid, 2),
            'remaining_amount' => max(round((float) $project->total_amount - $acceptedPaid, 2), 0),
        ]);
    }

    private function acceptedAdvanceSum(int $projectId): float
    {
        $sum = (float) CashTransfer::query()
            ->where('project_id', $projectId)
            ->where('status', 'accepted')
            ->sum('usd_amount');

        return round($sum, 2);
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

    private function extractAdvancePayment(array $lead): float
    {
        foreach ((array) ($lead['custom_fields_values'] ?? []) as $field) {
            $fieldName = mb_strtolower(trim((string) ($field['field_name'] ?? '')));
            $fieldCode = mb_strtolower(trim((string) ($field['field_code'] ?? '')));

            $isAdvance = in_array($fieldName, [
                'advance payment',
                'аванс',
                'предоплата',
                'передплата',
            ], true) || in_array($fieldCode, ['advance_payment', 'advance'], true);

            if (!$isAdvance) {
                continue;
            }

            foreach ((array) ($field['values'] ?? []) as $valueRow) {
                $raw = str_replace(',', '.', (string) ($valueRow['value'] ?? '0'));
                if (is_numeric($raw)) {
                    return max((float) $raw, 0);
                }
            }
        }

        return 0.0;
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
