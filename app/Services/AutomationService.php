<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class AutomationService
{
    public function fxUpdate(): array
    {
        $base = rtrim(config('services.automation.url'), '/');

        $resp = Http::timeout(60)
            ->acceptJson()
            ->post($base . '/fx');

        if (!$resp->successful()) {
            return [
                'ok' => false,
                'status' => $resp->status(),
                'body' => $resp->body(),
            ];
        }

        return [
            'ok' => true,
            'data' => $resp->json(),
        ];
    }
}
