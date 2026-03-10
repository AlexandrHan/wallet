<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AutomationDebug
{
    public function handle(Request $request, Closure $next)
    {
        if (!config('app.debug') || !env('DEBUG_AUTOMATION')) {
            abort(404);
        }

        return $next($request);
    }
}
