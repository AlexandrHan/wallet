<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AmoSearchService
{
    private string $baseUrl;
    private array  $userCache    = [];   // userId    → name
    private array  $leadCache    = [];   // leadId    → ['name', 'client', 'contact_ids']
    private array  $contactCache = [];   // contactId → name

    public function __construct(private AmoCrmService $amo)
    {
        $this->baseUrl = 'https://' . config('services.amocrm.domain') . '/api/v4';
    }

    /**
     * Search amoCRM for leads and notes containing $query.
     * Returns up to 5 results grouped by deal, ranked by relevance.
     *
     * @return array{type:string, entity_name:string, client:string, author:string, text:string, date:string}[]
     */
    public function search(string $query): array
    {
        $query = trim($query);
        if (mb_strlen($query) < 2) return [];

        try {
            $token = $this->amo->getAccessToken();
        } catch (\Throwable $e) {
            Log::warning('AmoSearchService: cannot get token', ['err' => $e->getMessage()]);
            return [];
        }

        // ── 1. Search leads (contact IDs come embedded, names resolved later) ─
        $leadItems = [];
        try {
            $data      = $this->apiGet('/leads', ['query' => $query, 'limit' => 15, 'with' => 'contacts'], $token);
            $leadItems = $data['_embedded']['leads'] ?? [];
            foreach ($leadItems as $lead) {
                $this->cacheLeadMeta($lead);
            }
        } catch (\Throwable $e) {
            Log::warning('AmoSearchService: leads search failed', ['err' => $e->getMessage()]);
        }

        // ── 2. Search notes globally ──────────────────────────────────────────
        $noteItems = [];
        try {
            $data      = $this->apiGet('/leads/notes', ['query' => $query, 'limit' => 25], $token);
            $noteItems = $data['_embedded']['notes'] ?? [];
        } catch (\Throwable $e) {
            Log::warning('AmoSearchService: global notes search failed', ['err' => $e->getMessage()]);
        }

        // ── 3. Fallback: scan notes of found leads if global search empty ─────
        if (empty($noteItems) && !empty($leadItems)) {
            try {
                $ids  = array_column($leadItems, 'id');
                $data = $this->apiGet('/leads/notes', ['filter' => ['entity_id' => $ids], 'limit' => 50], $token);
                $noteItems = array_values(array_filter(
                    $data['_embedded']['notes'] ?? [],
                    fn($n) => mb_stripos($n['params']['text'] ?? '', $query) !== false
                ));
            } catch (\Throwable $e) {
                Log::warning('AmoSearchService: per-lead notes failed', ['err' => $e->getMessage()]);
            }
        }

        // ── 4. Load leads for any note whose lead ID is not cached yet ────────
        $unknownIds = array_values(array_diff(
            array_unique(array_map(fn($n) => (int)($n['entity_id'] ?? 0), $noteItems)),
            array_keys($this->leadCache)
        ));
        if (!empty($unknownIds)) {
            $this->batchLoadLeads($unknownIds, $token);
        }

        // ── 5. Resolve client names for all cached leads ──────────────────────
        //      Tier 1: batch fetch contacts by their IDs
        //      Tier 2: fallback via filter[leads_id][] for leads still empty
        $this->batchResolveContacts($token);
        $this->resolveRemainingClients($token);

        // ── 6. Build raw results ──────────────────────────────────────────────
        $rawResults = [];

        foreach ($leadItems as $lead) {
            $lid     = (int) $lead['id'];
            $cached  = $this->leadCache[$lid] ?? [];
            $rawResults[] = [
                'type'        => 'deal',
                'entity_id'   => $lid,
                'entity_name' => $cached['name'] ?? "Угода #{$lid}",
                'client'      => $cached['client'] ?? '',
                'author'      => $this->resolveUser($lead['responsible_user_id'] ?? 0, $token),
                'text'        => $this->buildLeadSummary($lead),
                'date'        => $this->ts($lead['created_at'] ?? 0),
            ];
        }

        foreach ($noteItems as $note) {
            $text = $note['params']['text'] ?? ($note['params']['service'] ?? '');
            if (mb_strlen(trim($text)) < 5) continue;

            $lid    = (int) ($note['entity_id'] ?? 0);
            $cached = $this->leadCache[$lid] ?? [];

            $rawResults[] = [
                'type'        => 'note',
                'entity_id'   => $lid,
                'entity_name' => $cached['name']   ?? "Угода #{$lid}",
                'client'      => $cached['client'] ?? '',
                'author'      => $this->resolveUser($note['created_by'] ?? 0, $token),
                'text'        => $this->excerpt($text, $query),
                'date'        => $this->ts($note['created_at'] ?? 0),
            ];
        }

        return $this->groupAndRank($rawResults, $query, 5);
    }

    // ── Contact resolution (3-tier) ───────────────────────────────────────────

    /**
     * Store lead name and embedded contact IDs (names resolved separately).
     * amoCRM embeds only {id, is_main} in leads search — NOT the contact name.
     */
    private function cacheLeadMeta(array $lead): void
    {
        $id = (int) $lead['id'];
        $this->leadCache[$id] = [
            'name'        => $lead['name'] ?? "Угода #{$id}",
            'client'      => '',
            'contact_ids' => array_column($lead['_embedded']['contacts'] ?? [], 'id'),
        ];
    }

    /**
     * Tier 1: collect all unique contact IDs across cached leads,
     * batch-fetch their names in one API call, fill client in leadCache.
     */
    private function batchResolveContacts(string $token): void
    {
        // Map contactId → [leadId, …] for contacts we haven't fetched yet
        $contactToLeads = [];
        foreach ($this->leadCache as $leadId => $cached) {
            if ($cached['client']) continue; // already resolved
            foreach ($cached['contact_ids'] as $cId) {
                if (isset($this->contactCache[$cId])) {
                    // Already in cache — just assign
                    if (!$this->leadCache[$leadId]['client']) {
                        $this->leadCache[$leadId]['client'] = $this->contactCache[$cId];
                    }
                } else {
                    $contactToLeads[$cId][] = $leadId;
                }
            }
        }

        if (empty($contactToLeads)) return;

        foreach (array_chunk(array_keys($contactToLeads), 50) as $chunk) {
            $params = ['limit' => count($chunk)];
            foreach ($chunk as $cId) {
                $params['filter[id][]'][] = $cId;
            }
            try {
                $data = $this->apiGet('/contacts', $params, $token);
                foreach ($data['_embedded']['contacts'] ?? [] as $contact) {
                    $cId  = (int) $contact['id'];
                    $name = $contact['name'] ?? trim(($contact['first_name'] ?? '') . ' ' . ($contact['last_name'] ?? ''));
                    $this->contactCache[$cId] = $name;

                    foreach ($contactToLeads[$cId] ?? [] as $leadId) {
                        if (!$this->leadCache[$leadId]['client']) {
                            $this->leadCache[$leadId]['client'] = $name;
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::debug('AmoSearchService: contact batch failed', ['err' => $e->getMessage()]);
            }
        }
    }

    /**
     * Tier 2: for leads that still have no client (no embedded contact IDs),
     * query contacts filtered by leads_id — one call per unknown lead.
     */
    private function resolveRemainingClients(string $token): void
    {
        foreach ($this->leadCache as $leadId => &$cached) {
            if ($cached['client']) continue;

            try {
                $data     = $this->apiGet('/contacts', ['filter' => ['leads_id' => [$leadId]], 'limit' => 1], $token);
                $contacts = $data['_embedded']['contacts'] ?? [];
                if (!empty($contacts[0])) {
                    $c    = $contacts[0];
                    $name = $c['name'] ?? trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? ''));
                    $cached['client'] = trim($name);
                }
            } catch (\Throwable) {}
        }
        unset($cached);
    }

    // ── Lead batch loader ─────────────────────────────────────────────────────

    private function batchLoadLeads(array $ids, string $token): void
    {
        foreach (array_chunk($ids, 25) as $chunk) {
            $params = ['with' => 'contacts', 'limit' => count($chunk)];
            foreach ($chunk as $id) {
                $params['filter[id][]'][] = $id;
            }
            try {
                $data = $this->apiGet('/leads', $params, $token);
                foreach ($data['_embedded']['leads'] ?? [] as $lead) {
                    $this->cacheLeadMeta($lead);
                }
            } catch (\Throwable $e) {
                Log::debug('AmoSearchService: batch lead load failed', ['err' => $e->getMessage()]);
            }
        }
    }

    // ── Grouping & ranking ────────────────────────────────────────────────────

    private function groupAndRank(array $raw, string $query, int $limit): array
    {
        $groups = [];
        foreach ($raw as $r) {
            $lid = $r['entity_id'];
            if (!isset($groups[$lid])) {
                $groups[$lid] = ['deal' => null, 'notes' => [], 'score' => 0];
            }
            if ($r['type'] === 'deal') {
                $groups[$lid]['deal'] = $r;
            } else {
                $groups[$lid]['notes'][] = $r;
            }
            $groups[$lid]['score'] = max($groups[$lid]['score'], $this->relevanceScore($r, $query));
        }

        uasort($groups, fn($a, $b) => $b['score'] <=> $a['score']);

        $final = [];
        foreach ($groups as $group) {
            $deal  = $group['deal'];
            $notes = $group['notes'];

            usort($notes, fn($a, $b) => $this->relevanceScore($b, $query) <=> $this->relevanceScore($a, $query));
            $bestNote = $notes[0] ?? null;

            $base = $deal ?? $bestNote;
            if (!$base) continue;

            $final[] = [
                'type'        => 'deal',
                'entity_id'   => $base['entity_id'],
                'entity_name' => $base['entity_name'],
                'client'      => $base['client'],
                'author'      => $base['author'],
                'text'        => $bestNote ? $bestNote['text'] : $base['text'],
                'date'        => $bestNote ? $bestNote['date'] : $base['date'],
            ];

            if (count($final) >= $limit) break;
        }

        return $final;
    }

    private function relevanceScore(array $r, string $query): int
    {
        $score = 0;
        $text  = $r['text'] ?? '';
        $len   = mb_strlen($text);

        if (mb_stripos($text, $query) !== false) $score += 100;
        if ($len > 20)  $score += 15;
        if ($len > 60)  $score += 15;
        if ($len > 120) $score += 10;
        if ($r['type'] === 'note') $score += 5;

        return $score;
    }

    // ── HTTP ──────────────────────────────────────────────────────────────────

    private function apiGet(string $path, array $params, string $token): array
    {
        $resp = Http::timeout(12)
            ->withToken($token)
            ->get($this->baseUrl . $path, $params);

        if (!$resp->successful()) {
            Log::debug('AmoSearchService: non-2xx', ['path' => $path, 'status' => $resp->status()]);
            return [];
        }

        return $resp->json() ?? [];
    }

    // ── User resolution ───────────────────────────────────────────────────────

    private function resolveUser(int $userId, string $token): string
    {
        if (!$userId) return '';
        if (array_key_exists($userId, $this->userCache)) return $this->userCache[$userId];

        try {
            $data = $this->apiGet("/users/{$userId}", [], $token);
            return $this->userCache[$userId] = $data['name'] ?? '';
        } catch (\Throwable) {
            return $this->userCache[$userId] = '';
        }
    }

    // ── Text helpers ──────────────────────────────────────────────────────────

    private function buildLeadSummary(array $lead): string
    {
        $parts = [];
        if (!empty($lead['name'])) $parts[] = $lead['name'];

        foreach ($lead['custom_fields_values'] ?? [] as $cf) {
            foreach ($cf['values'] ?? [] as $v) {
                if (!empty($v['value']) && is_string($v['value'])) {
                    $parts[] = $v['value'];
                    if (count($parts) >= 3) break 2;
                }
            }
        }

        return implode(' · ', array_unique($parts));
    }

    private function excerpt(string $text, string $query, int $len = 160): string
    {
        $pos = mb_stripos($text, $query);
        if ($pos === false) {
            return mb_substr($text, 0, $len) . (mb_strlen($text) > $len ? '…' : '');
        }

        $start   = max(0, $pos - 50);
        $excerpt = mb_substr($text, $start, $len);

        if ($start > 0)                        $excerpt = '…' . $excerpt;
        if ($start + $len < mb_strlen($text))  $excerpt .= '…';

        return $excerpt;
    }

    private function ts(int $timestamp): string
    {
        return $timestamp > 0 ? date('d.m.Y', $timestamp) : '';
    }
}
