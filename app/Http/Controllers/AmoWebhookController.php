<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessAmoWebhookJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AmoWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        // Basic protection: require a shared secret (header) to avoid open webhook abuse.
        // If amoCRM doesn't support custom headers, this can be replaced with a query param check.
        $secret = config('services.amocrm.webhook_secret');
        if ($secret && $request->header('X-Amocrm-Secret') !== $secret) {
            abort(403);
        }

        $payload = $request->all();

        if (empty($payload)) {
            Log::warning('amoCRM webhook received empty payload');

            return response()->json([
                'ok' => false,
                'message' => 'Empty payload',
            ], 422);
        }

        ProcessAmoWebhookJob::dispatch($payload);

        return response()->json([
            'ok' => true,
            'queued' => true,
        ]);
    }
}
