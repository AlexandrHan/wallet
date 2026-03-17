<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ZippyStockService
{
    private string $baseUrl;
    private string $staticApiKey;
    private string $authPath;
    private string $login;
    private string $password;
    private string $itemsPath;
    private int $timeout;

    public function __construct()
    {
        $this->baseUrl      = rtrim((string) config('services.zippy.base_url', ''), '/');
        $this->staticApiKey = (string) config('services.zippy.api_key', '');
        $this->authPath     = ltrim((string) config('services.zippy.auth_path', ''), '/');
        $this->login        = (string) config('services.zippy.login', '');
        $this->password     = (string) config('services.zippy.password', '');
        $this->itemsPath    = ltrim((string) config('services.zippy.stock_path', 'api/items'), '/');
        $this->timeout      = (int) config('services.zippy.timeout', 30);
    }

    /**
     * Fetch stock from Zippy CRM, normalize, upsert into solarglass_stock.
     * Strategy: call itemlist (names) + getqty (quantities), merge by item_code.
     *
     * @return array{updated: int, created: int, skipped: int, duration: float}
     */
    public function sync(): array
    {
        $startedAt = microtime(true);

        $token = $this->resolveToken();

        // 1. Fetch item names
        $itemlistRaw = $this->callMethod($token, 'itemlist', []);
        $nameMap = [];
        foreach ((array) $itemlistRaw as $row) {
            if (!is_array($row) || empty($row['item_code'])) continue;
            $code = (string) $row['item_code'];
            $name = $this->decodeBase64IfNeeded(trim((string) ($row['itemname'] ?? $row['item_name'] ?? '')));
            $nameMap[$code] = $name !== '' ? $name : null;
        }

        // 2. Fetch quantities
        $qtyRaw = $this->callMethod($token, 'getqty', []);
        $qtyMap = [];
        foreach ((array) $qtyRaw as $row) {
            if (!is_array($row) || empty($row['item_code'])) continue;
            $qtyMap[(string) $row['item_code']] = (float) ($row['qty'] ?? 0);
        }

        // 3. Merge: all item_codes from both sources
        $allCodes = array_unique(array_merge(array_keys($nameMap), array_keys($qtyMap)));

        $updated = 0;
        $created = 0;
        $skipped = 0;

        foreach ($allCodes as $code) {
            $code = trim($code);
            if ($code === '') {
                $skipped++;
                continue;
            }

            $name = $nameMap[$code] ?? null;
            $qty  = $qtyMap[$code] ?? 0.0;

            $exists = DB::table('solarglass_stock')
                ->where('item_code', $code)
                ->exists();

            DB::table('solarglass_stock')->updateOrInsert(
                ['item_code' => $code],
                [
                    'item_name'  => $name,
                    'qty'        => $qty,
                    'meta'       => json_encode([
                        'source'    => 'zippy',
                        'synced_at' => now()->toIso8601String(),
                    ]),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            $exists ? $updated++ : $created++;
        }

        return [
            'updated'  => $updated,
            'created'  => $created,
            'skipped'  => $skipped,
            'duration' => round(microtime(true) - $startedAt, 2),
        ];
    }

    /**
     * Call a JSON-RPC method on the items endpoint and return the result array.
     */
    private function callMethod(string $token, string $method, array $params): array
    {
        $url = $this->baseUrl . '/' . $this->itemsPath;

        $response = Http::timeout($this->timeout)
            ->acceptJson()
            ->withToken($token)
            ->post($url, [
                'jsonrpc' => '2.0',
                'id'      => 1,
                'method'  => $method,
                'params'  => $params ?: new \stdClass(),
            ]);

        if (!$response->successful()) {
            Log::error('ZippyStockService: request failed', [
                'method' => $method,
                'status' => $response->status(),
                'body'   => mb_substr($response->body(), 0, 500),
            ]);
            throw new \RuntimeException("Zippy [{$method}] HTTP " . $response->status());
        }

        $body = $response->json();

        if (isset($body['error'])) {
            $code    = $body['error']['code'] ?? 0;
            $message = $body['error']['message'] ?? 'unknown';
            Log::error('ZippyStockService: JSON-RPC error', compact('method', 'code', 'message'));
            throw new \RuntimeException("Zippy [{$method}] error [{$code}]: {$message}");
        }

        $result = $body['result'] ?? [];
        return is_array($result) ? $result : [];
    }

    /**
     * Resolve Bearer token: use static key, or obtain via JSON-RPC login.
     */
    private function resolveToken(): string
    {
        if ($this->staticApiKey !== '') {
            return $this->staticApiKey;
        }

        if ($this->authPath === '' || $this->login === '') {
            throw new \RuntimeException(
                'Zippy auth not configured. Set ZIPPY_API_KEY or ZIPPY_AUTH_PATH + ZIPPY_API_LOGIN + ZIPPY_API_PASSWORD.'
            );
        }

        $authUrl  = $this->baseUrl . '/' . $this->authPath;
        $response = Http::timeout($this->timeout)
            ->acceptJson()
            ->post($authUrl, [
                'jsonrpc' => '2.0',
                'id'      => 1,
                'method'  => 'token',
                'params'  => ['login' => $this->login, 'password' => $this->password],
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Zippy auth failed: HTTP ' . $response->status());
        }

        $body = $response->json();
        if (isset($body['error'])) {
            throw new \RuntimeException('Zippy auth error: ' . ($body['error']['message'] ?? 'unknown'));
        }

        $token = $body['result'] ?? null;
        if (!$token || !is_string($token)) {
            throw new \RuntimeException('Zippy auth: unexpected token response.');
        }

        return $token;
    }

    /**
     * Decode base64-encoded string if it is valid base64 + valid UTF-8.
     */
    private function decodeBase64IfNeeded(string $value): string
    {
        if (strlen($value) < 8 || !preg_match('/^[A-Za-z0-9+\/]+=*$/', $value)) {
            return $value;
        }

        $decoded = base64_decode($value, strict: true);
        if ($decoded === false || !mb_check_encoding($decoded, 'UTF-8') || trim($decoded) === '') {
            return $value;
        }

        return $decoded;
    }
}
