<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LocalLLMService
{
    private string $endpoint;
    private int $timeout;

    public function __construct()
    {
        $base           = rtrim((string) config('services.openclaw.url', 'http://localhost:9000'), '/');
        $this->endpoint = $base . '/ai/local';
        $this->timeout  = (int) config('services.openclaw.timeout', 60);
    }

    /**
     * @return array{response: string, duration_ms: int, status_code: int}
     */
    public function ask(string $question, array $context = [], string $system = ''): array
    {
        $started = microtime(true);

        $payload = [
            'question' => $question,
            'context'  => $context,
            'system'   => $system ?: null,
        ];

        try {
            $response = Http::timeout($this->timeout)
                ->acceptJson()
                ->post($this->endpoint, $payload);

            $durationMs = (int) round((microtime(true) - $started) * 1000);
            $statusCode = $response->status();

            if (!$response->successful()) {
                Log::warning('LocalLLMService: non-2xx', ['status' => $statusCode]);
                return [
                    'response'    => 'Локальний AI тимчасово недоступний (HTTP ' . $statusCode . ').',
                    'duration_ms' => $durationMs,
                    'status_code' => $statusCode,
                ];
            }

            $body = $response->json();
            $text = $body['response'] ?? $body['answer'] ?? $body['text'] ?? $body['message'] ?? $response->body();

            return [
                'response'    => (string) $text,
                'duration_ms' => $durationMs,
                'status_code' => $statusCode,
            ];
        } catch (\Throwable $e) {
            $durationMs = (int) round((microtime(true) - $started) * 1000);
            Log::error('LocalLLMService: request failed', ['error' => $e->getMessage()]);

            return [
                'response'    => 'Не вдалося підключитися до локального AI: ' . $e->getMessage(),
                'duration_ms' => $durationMs,
                'status_code' => 0,
            ];
        }
    }
}
