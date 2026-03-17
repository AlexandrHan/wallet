<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Services\AI\AIChatRouter;
use App\Services\AI\AIAgentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AIChatController extends Controller
{
    public function __construct(
        private AIChatRouter  $router,
        private AIAgentService $agent,
    ) {}

    public function chat(Request $request)
    {
        $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'model'   => ['sometimes', 'string', 'in:local,claude'],
        ]);

        $question  = trim($request->input('message'));
        $model     = $request->input('model', 'local');
        $user      = auth()->user();

        $context = $this->agent->collectContext();
        $system  = 'You are a financial analyst for SolarGlass company. '
                 . 'Analyze expenses, projects, stock and financial flows. '
                 . 'Provide forecasts and clear financial conclusions. '
                 . 'Answer in Ukrainian. Be concise and actionable.';

        $result = $this->router->handle($question, $model, $context, $system);

        // Log the interaction
        try {
            DB::table('ai_logs')->insert([
                'user_id'     => $user?->id,
                'question'    => $question,
                'response'    => $result['response'],
                'status_code' => $result['status_code'],
                'duration_ms' => $result['duration_ms'],
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('AIChatController: failed to log AI interaction', ['error' => $e->getMessage()]);
        }

        return response()->json([
            'response'    => $result['response'],
            'duration_ms' => $result['duration_ms'],
            'model_used'  => $result['model_used'],
        ]);
    }
}
