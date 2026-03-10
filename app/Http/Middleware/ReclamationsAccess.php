<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ReclamationsAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $u = $request->user();
        if (!$u) return $next($request);

        // Дозволено: owner, foreman, sunfix
        if (in_array($u->role, ['owner', 'foreman', 'worker', 'sunfix', 'sunfix_manager'], true)) {
            return $next($request);
        }

        abort(403);
    }
}










