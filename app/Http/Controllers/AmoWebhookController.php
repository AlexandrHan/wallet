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
