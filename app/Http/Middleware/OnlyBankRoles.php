<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class OnlyBankRoles
{
    public function handle(Request $request, Closure $next)
    {
        $u = $request->user();

        if (!$u) abort(403);

        if (!in_array($u->role, ['owner', 'accountant'], true)) {
            abort(403);
        }

        return $next($request);
    }
}
