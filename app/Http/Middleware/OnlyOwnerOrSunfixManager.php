<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class OnlyOwnerOrSunfixManager
{
    public function handle(Request $request, Closure $next)
    {
        $u = $request->user();

        if (!$u || !in_array($u->role, ['owner', 'sunfix_manager'], true)) {
            abort(403);
        }

        return $next($request);
    }
}
