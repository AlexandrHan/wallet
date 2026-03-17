<?php

namespace App\Services\AI;

class AIChatRouter
{
    public function __construct(
        private LocalLLMService $local,
        private ClaudeService   $claude,
    ) {}

    /**
     * Route question to the correct AI service.
     *
     * @param  string  $model  'local' | 'claude'
     * @return array{response: string, duration_ms: int, status_code: int, model_used: string}
     */
    public function handle(string $question, string $model, array $context = [], string $system = ''): array
    {
        // Fallback: if Claude key is missing, switch to local
        if ($model === 'claude' && !$this->claude->isAvailable()) {
            $model = 'local';
        }

        if ($model === 'claude') {
            $result             = $this->claude->ask($question, $context, $system);
            $result['model_used'] = 'claude';
        } else {
            $result             = $this->local->ask($question, $context, $system);
            $result['model_used'] = 'local';
        }

        return $result;
    }
}
