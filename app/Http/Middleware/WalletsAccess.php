<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class WalletsAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $u = $request->user();
        if (!$u) return abort(401);

        // В гаманці дозволені тільки owner + accountant
        // (прораб НЕ має бачити банки/обмінник за твоєю вимогою)
        if (in_array($u->role, ['owner', 'accountant'], true)) {
            return $next($request);
        }

        abort(403);
    }
}
