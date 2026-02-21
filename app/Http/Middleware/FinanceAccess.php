<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class FinanceAccess
{
    public function handle(Request $request, Closure $next)
    {
        // ✅ перевіряємо тільки finance-сторінку і finance API
        $isFinance =
            $request->is('finance*') ||
            $request->is('api/sales-projects*') ||
            $request->is('api/cash-transfers*');

        if ($isFinance) {
            $u = $request->user();
            if (!$u || !in_array($u->role, ['owner', 'ntv'], true)) {
                abort(403);
            }
        }

        return $next($request);
    }
}