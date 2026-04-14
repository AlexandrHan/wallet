<?php

namespace App\Services;

use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PushService
{
    private const SCOPES  = ['https://www.googleapis.com/auth/firebase.messaging'];
    private const FCM_URL = 'https://fcm.googleapis.com/v1/projects/%s/messages:send';

    // ── OAuth token via Service Account ──────────────────────────────────────

    private function accessToken(): ?string
    {
        $path = config('services.firebase.credentials');

        if (!$path) {
            Log::error('PushService: FIREBASE_CREDENTIALS is not set in .env');
            return null;
        }

        if (!file_exists($path)) {
            Log::error('PushService: credentials file not found', ['path' => $path]);
            return null;
        }

        try {
            $json  = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            $cred  = new ServiceAccountCredentials(self::SCOPES, $json);
            $token = $cred->fetchAuthToken()['access_token'] ?? null;

            if (!$token) {
                Log::error('PushService: OAuth token is empty — check service account permissions');
                return null;
            }

            return $token;
        } catch (\Throwable $e) {
            Log::error('PushService: failed to fetch OAuth token', ['error' => $e->getMessage()]);
            return null;
        }
    }

    // ── Send FCM HTTP v1 push ─────────────────────────────────────────────────

    /**
     * @param array $data  Extra key-value pairs sent in the data payload
     * @return array{success: bool, status?: int, error?: string}
     */
    public function send(
        string $deviceToken,
        string $title,
        string $body,
        string $url  = '/',
        string $type = 'system',
        array  $data = []
    ): array {
        if (!$deviceToken) {
            return ['success' => false, 'error' => 'empty device token'];
        }

        $projectId = config('services.firebase.project_id');
        if (!$projectId) {
            Log::error('PushService: FIREBASE_PROJECT_ID is not set');
            return ['success' => false, 'error' => 'missing project_id'];
        }

        $accessToken = $this->accessToken();
        if (!$accessToken) {
            return ['success' => false, 'error' => 'could not get access token'];
        }

        $badge = isset($data['badge']) ? (int) $data['badge'] : 0;

        $payload = [
            'message' => [
                'token' => $deviceToken,
                'notification' => [
                    'title' => $title,
                    'body'  => $body,
                ],
                'android' => [
                    'priority'     => 'high',
                    'notification' => ['sound' => 'default'],
                ],
                'apns' => [
                    'payload' => [
                        'aps' => [
                            'sound'             => 'default',
                            'badge'             => $badge,
                            'content-available' => 1,
                        ],
                    ],
                ],
                'webpush' => [
                    'fcm_options'  => ['link' => $url],
                    'notification' => [
                        'icon'  => '/img/logo.png',
                        'badge' => '/img/logo.png',
                    ],
                ],
                'data' => array_merge([
                    'url'   => $url,
                    'type'  => $type,
                    'badge' => (string) $badge,
                ], array_map('strval', $data)),
            ],
        ];

        try {
            $response = Http::timeout(8)
                ->withToken($accessToken)
                ->post(sprintf(self::FCM_URL, $projectId), $payload);

            if ($response->successful()) {
                return ['success' => true];
            }

            $errorCode = $response->json('error.details.0.errorCode')
                ?? $response->json('error.message')
                ?? '';

            // Token is invalid — clear it from DB so we stop sending to a dead device
            if ($response->status() === 404 || str_contains($errorCode, 'UNREGISTERED')) {
                \Illuminate\Support\Facades\DB::table('users')
                    ->where('push_token', $deviceToken)
                    ->update(['push_token' => null]);
                Log::info('PushService: cleared stale push token', ['token' => substr($deviceToken, 0, 20) . '…']);
            } else {
                Log::warning('PushService: FCM returned error', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                    'token'  => substr($deviceToken, 0, 20) . '…',
                ]);
            }

            return [
                'success' => false,
                'status'  => $response->status(),
                'error'   => $errorCode ?: $response->body(),
            ];
        } catch (\Throwable $e) {
            Log::error('PushService: HTTP request failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
