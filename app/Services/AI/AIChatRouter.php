<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Log;

class AIChatRouter
{
    public function __construct(
        private LocalLLMService $local,
        private ClaudeService   $claude,
    ) {}

    /**
     * Route question to the correct AI service.
     * If Claude is selected but unavailable or returns an error, falls back to local.
     *
     * @param  string  $model  'local' | 'claude'
     * @return array{response: string, duration_ms: int, status_code: int, model_used: string}
     */
    public function handle(string $question, string $model, array $context = [], string $system = ''): array
    {
        // Fallback: if Claude key is missing, switch to local immediately
        if ($model === 'claude' && !$this->claude->isAvailable()) {
            Log::channel('ai')->warning('AIChatRouter: Claude key missing, falling back to local');
            $model = 'local';
        }

        if ($model === 'claude') {
            $result = $this->claude->ask($question, $context, $system);

            // If Claude returned a non-2xx response, try local as fallback
            if (($result['status_code'] ?? 0) >= 400 || ($result['status_code'] ?? 0) === 0) {
                Log::channel('ai')->warning('AIChatRouter: Claude failed (status ' . ($result['status_code'] ?? 0) . '), falling back to local');
                $localResult             = $this->local->ask($question, $context, $system);
                $localResult['model_used'] = 'local-fallback';
                return $localResult;
            }

            $result['model_used'] = 'claude';
            return $result;
        }

        $result             = $this->local->ask($question, $context, $system);
        $result['model_used'] = 'local';
        return $result;
    }
}
