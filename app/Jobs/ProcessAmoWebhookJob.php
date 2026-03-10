<?php

namespace App\Jobs;

use App\Services\AmoCrmService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessAmoWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public array $payload)
    {
    }

    public function handle(AmoCrmService $amoCrmService): void
    {
        $events = $amoCrmService->extractWebhookEvents($this->payload);

        foreach ($events as $event) {
            try {
                $amoCrmService->handleWebhookEvent(
                    (string) ($event['type'] ?? 'lead.update'),
                    (array) ($event['lead'] ?? [])
                );
            } catch (\Throwable $e) {
                Log::error('ProcessAmoWebhookJob failed for event', [
                    'event' => $event,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
