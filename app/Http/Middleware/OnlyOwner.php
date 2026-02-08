<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class OnlyOwner
{
    public function handle(Request $request, Closure $next)
    {
        $u = $request->user();

        if (!$u || $u->role !== 'owner') abort(403);

        return $next($request);
    }
}
