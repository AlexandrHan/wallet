<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ClaudeService
{
    private string $apiKey;
    private string $apiUrl  = 'https://api.anthropic.com/v1/messages';
    private string $model   = 'claude-sonnet-4-6';
    private int    $timeout = 90;

    public function __construct()
    {
        $this->apiKey = (string) config('services.anthropic.key', env('ANTHROPIC_API_KEY', ''));
    }

    public function isAvailable(): bool
    {
        return $this->apiKey !== '';
    }

    /**
     * @return array{response: string, duration_ms: int, status_code: int}
     */
    public function ask(string $question, array $context = [], string $system = ''): array
    {
        if (!$this->isAvailable()) {
            return [
                'response'    => 'Claude API ключ не налаштовано.',
                'duration_ms' => 0,
                'status_code' => 0,
            ];
        }

        $started = microtime(true);

        $systemPrompt = $system ?: (
            'You are a financial analyst for SolarGlass company. '
            . 'Analyze expenses, projects, stock and financial flows. '
            . 'Provide forecasts and clear financial conclusions. '
            . 'Answer in Ukrainian. Be concise and actionable.'
        );

        $contextJson = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $userPrompt  = "Питання:\n{$question}\n\nФінансовий контекст:\n{$contextJson}";

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'x-api-key'         => $this->apiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type'      => 'application/json',
                ])
                ->post($this->apiUrl, [
                    'model'      => $this->model,
                    'max_tokens' => 1024,
                    'system'     => $systemPrompt,
                    'messages'   => [
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                ]);

            $durationMs = (int) round((microtime(true) - $started) * 1000);
            $statusCode = $response->status();

            if (!$response->successful()) {
                $body = $response->json();
                $msg  = $body['error']['message'] ?? ('HTTP ' . $statusCode);
                Log::warning('ClaudeService: API error', ['status' => $statusCode, 'msg' => $msg]);
                return [
                    'response'    => 'Claude тимчасово недоступний: ' . $msg,
                    'duration_ms' => $durationMs,
                    'status_code' => $statusCode,
                ];
            }

            $body = $response->json();
            $text = $body['content'][0]['text'] ?? '';

            if (!$text) {
                return [
                    'response'    => 'Claude повернув порожню відповідь.',
                    'duration_ms' => $durationMs,
                    'status_code' => $statusCode,
                ];
            }

            return [
                'response'    => $text,
                'duration_ms' => $durationMs,
                'status_code' => $statusCode,
            ];
        } catch (\Throwable $e) {
            $durationMs = (int) round((microtime(true) - $started) * 1000);
            Log::error('ClaudeService: request failed', ['error' => $e->getMessage()]);

            return [
                'response'    => 'Помилка підключення до Claude: ' . $e->getMessage(),
                'duration_ms' => $durationMs,
                'status_code' => 0,
            ];
        }
    }
}
