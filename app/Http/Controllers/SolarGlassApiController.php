<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SolarGlassApiController extends Controller
{
    private const ERR_INVALID_LOGIN = -1000;
    private const ERR_USER_NOT_FOUND = -1001;
    private const ERR_TOKEN_EXPIRED = -1002;
    private const ERR_PARSE = -1003;
    private const ERR_INVALID_REQUEST = -1004;
    private const ERR_INVALID_METHOD = -1005;

    public function jsonRpc(Request $request)
    {
        $body = $request->getContent();
        if (trim($body) === '') {
            return $this->error(-1003, 'Empty request body');
        }

        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->error(-1003, 'Invalid JSON: ' . json_last_error_msg());
        }

        // Support batch requests
        if (is_array($data) && array_is_list($data)) {
            $responses = [];
            foreach ($data as $item) {
                $resp = $this->handleJsonRpc($request, $item);
                if ($resp !== null) {
                    $responses[] = $resp;
                }
            }
            return response()->json($responses);
        }

        $resp = $this->handleJsonRpc($request, $data);
        if ($resp === null) {
            // notification
            return response()->noContent();
        }
        return response()->json($resp);
    }

    private function handleJsonRpc(Request $request, $payload)
    {
        if (!is_array($payload)) {
            return $this->makeError(null, self::ERR_INVALID_REQUEST, 'Request must be an object');
        }

        $id = $payload['id'] ?? null;
        $jsonrpc = $payload['jsonrpc'] ?? null;
        $method = $payload['method'] ?? null;
        $params = $payload['params'] ?? [];

        if ($jsonrpc !== '2.0') {
            return $this->makeError($id, self::ERR_INVALID_REQUEST, 'jsonrpc must be "2.0"');
        }

        if (!$method || !is_string($method)) {
            return $this->makeError($id, self::ERR_INVALID_REQUEST, 'method is required');
        }

        // Methods that do not require auth
        if ($method === 'checkapi') {
            return $this->makeResult($id, ['ok' => true]);
        }

        if ($method === 'token') {
            return $this->handleToken($id, $params);
        }

        // All other methods require bearer token
        $token = $this->extractBearerToken($request);
        if (!$token || $token !== $this->getApiToken()) {
            return $this->makeError($id, self::ERR_TOKEN_EXPIRED, 'Invalid or missing token');
        }

        switch ($method) {
            case 'sync_stock':
                return $this->handleSyncStock($id, $params);
            case 'get_stock':
                return $this->handleGetStock($id, $params);
            default:
                return $this->makeError($id, self::ERR_INVALID_METHOD, 'Unknown method');
        }
    }

    private function handleToken($id, $params)
    {
        $login = $params['login'] ?? null;
        $password = $params['password'] ?? null;

        $expectedLogin = env('SOLARGLASS_API_LOGIN', 'solarglass');
        $expectedPassword = env('SOLARGLASS_API_PASSWORD', 'solarglass');

        if (!$login || !$password) {
            return $this->makeError($id, self::ERR_INVALID_REQUEST, 'login and password are required');
        }

        if (!hash_equals($expectedLogin, (string) $login)) {
            return $this->makeError($id, self::ERR_INVALID_LOGIN, 'Invalid login');
        }

        if (!hash_equals($expectedPassword, (string) $password)) {
            return $this->makeError($id, self::ERR_INVALID_LOGIN, 'Invalid password');
        }

        return $this->makeResult($id, ['token' => $this->getApiToken()]);
    }

    private function handleSyncStock($id, $params)
    {
        if (!isset($params['items']) || !is_array($params['items'])) {
            return $this->makeError($id, self::ERR_INVALID_REQUEST, 'Missing items array');
        }

        $items = $params['items'];
        $updated = 0;

        foreach ($items as $item) {
            if (!is_array($item) || empty($item['item_code'])) {
                continue;
            }
            $code = (string) $item['item_code'];
            $name = isset($item['item_name']) ? $this->decodeBase64IfNeeded((string) $item['item_name']) : null;
            $qty = isset($item['qty']) ? (float) $item['qty'] : 0;

            DB::table('solarglass_stock')->updateOrInsert(
                ['item_code' => $code],
                [
                    'item_name' => $name,
                    'qty' => $qty,
                    'updated_at' => now(),
                    'created_at' => now(),
                    'meta' => json_encode(['source' => 'zippy']),
                ]
            );
            $updated++;
        }

        return $this->makeResult($id, ['updated' => $updated]);
    }

    private function handleGetStock($id, $params)
    {
        $rows = DB::table('solarglass_stock')
            ->select('item_code', 'item_name', 'qty', 'updated_at')
            ->get();

        return $this->makeResult($id, ['items' => $rows]);
    }

    private function extractBearerToken(Request $request): ?string
    {
        $auth = $request->header('Authorization', '');
        if (!$auth) {
            return null;
        }

        if (!Str::startsWith($auth, 'Bearer ')) {
            return null;
        }

        return trim(substr($auth, 7));
    }

    private function getApiToken(): string
    {
        return (string) env('SOLARGLASS_API_TOKEN', '');
    }

    private function makeError($id, int $code, string $message)
    {
        return [
            'jsonrpc' => '2.0',
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
            'id' => $id,
        ];
    }

    private function decodeBase64IfNeeded(string $value): string
    {
        // If the value looks like base64 and successfully decodes, use decoded version.
        if (base64_decode($value, true) !== false) {
            $decoded = base64_decode($value);
            if ($decoded !== false && mb_strlen($decoded) > 0) {
                return $decoded;
            }
        }
        return $value;
    }

    private function makeResult($id, $result)
    {
        return [
            'jsonrpc' => '2.0',
            'result' => $result,
            'id' => $id,
        ];
    }

    private function error(int $code, string $message)
    {
        return response()->json([
            'jsonrpc' => '2.0',
            'error' => ['code' => $code, 'message' => $message],
            'id' => null,
        ]);
    }
}
