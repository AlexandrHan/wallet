<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Reads rows from a Google Spreadsheet via Sheets API v4.
 * Auth: service account (JSON key file) → short-lived OAuth2 token via JWT.
 *
 * Required env vars:
 *   GOOGLE_SHEETS_SPREADSHEET_ID  – the spreadsheet ID from the URL
 *   GOOGLE_SERVICE_ACCOUNT_PATH   – path to the service-account JSON key file
 *                                   (default: storage/app/private/google-service-account.json)
 */
class GoogleSheetsService
{
    private string $spreadsheetId;
    private array  $credentials;
    private ?string $accessToken = null;
    private int    $tokenExpiry  = 0;

    public function __construct()
    {
        $this->spreadsheetId = (string) config('services.google_sheets.spreadsheet_id', '');

        $path = (string) config(
            'services.google_sheets.service_account_path',
            storage_path('app/private/google-service-account.json')
        );

        if (!file_exists($path)) {
            throw new RuntimeException("Google service-account key not found: {$path}");
        }

        $json = file_get_contents($path);
        if ($json === false) {
            throw new RuntimeException("Cannot read Google service-account key: {$path}");
        }

        $this->credentials = (array) json_decode($json, true);

        if (empty($this->credentials['private_key']) || empty($this->credentials['client_email'])) {
            throw new RuntimeException('Invalid Google service-account JSON (missing private_key or client_email)');
        }
    }

    // ──────────────────────────────────────────────────────────────────────
    // Public API
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Returns all rows from <sheetName>!A:F as a 2-D array of strings.
     * Throws RuntimeException if the sheet tab does not exist or API fails.
     *
     * @return string[][]
     */
    public function getSheetRows(string $sheetName, string $range = 'A:F'): array
    {
        $token        = $this->getAccessToken();
        $encodedRange = rawurlencode("{$sheetName}!{$range}");
        $url          = "https://sheets.googleapis.com/v4/spreadsheets/{$this->spreadsheetId}/values/{$encodedRange}";

        $response = Http::withToken($token)
            ->timeout(15)
            ->get($url);

        if ($response->status() === 400 || $response->status() === 404) {
            throw new RuntimeException("Sheet tab '{$sheetName}' not found in spreadsheet (HTTP {$response->status()})");
        }

        if (!$response->successful()) {
            throw new RuntimeException("Google Sheets API error {$response->status()}: " . $response->body());
        }

        return (array) $response->json('values', []);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Auth helpers
    // ──────────────────────────────────────────────────────────────────────

    private function getAccessToken(): string
    {
        if ($this->accessToken && time() < $this->tokenExpiry - 30) {
            return $this->accessToken;
        }

        $now = time();
        $jwtPayload = [
            'iss'   => $this->credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/spreadsheets.readonly',
            'aud'   => 'https://oauth2.googleapis.com/token',
            'iat'   => $now,
            'exp'   => $now + 3600,
        ];

        $jwt      = $this->buildJwt($jwtPayload);
        $response = Http::asForm()
            ->timeout(15)
            ->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ]);

        if (!$response->successful()) {
            throw new RuntimeException('Google OAuth2 token error: ' . $response->body());
        }

        $data = $response->json();
        $this->accessToken = (string) ($data['access_token'] ?? '');
        $this->tokenExpiry = $now + (int) ($data['expires_in'] ?? 3600);

        if ($this->accessToken === '') {
            throw new RuntimeException('Google OAuth2 returned empty access_token');
        }

        return $this->accessToken;
    }

    private function buildJwt(array $payload): string
    {
        $header = $this->base64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $body   = $this->base64url(json_encode($payload));
        $input  = "{$header}.{$body}";

        $signature = '';
        if (!openssl_sign($input, $signature, $this->credentials['private_key'], OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Failed to sign Google JWT (openssl_sign)');
        }

        return "{$input}." . $this->base64url($signature);
    }

    private function base64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
