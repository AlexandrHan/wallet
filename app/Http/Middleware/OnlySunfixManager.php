<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OnlySunfixManager
{
    public function handle(Request $request, Closure $next): Response
    {
        $u = $request->user();

        // не sunfix_manager -> як було
        if (!$u || $u->role !== 'sunfix_manager') {
            return $next($request);
        }

        // sunfix_manager: дозволяємо тільки склад + поставки + рекламації + технічне + API цих модулів
        $allowed =
            // pages
            $request->is('stock') ||
            $request->is('deliveries*') ||
            $request->is('reclamations*') ||
            $request->is('stock/supplier-cash*') ||

            // api for stock/deliveries/products (+ reclamations якщо є)
            $request->is('api/stock*') ||
            $request->is('api/deliveries*') ||
            $request->is('api/products*') ||
            $request->is('api/reclamations*') ||
            $request->is('api/supplier-cash*') ||
            


            // logout + storage
            $request->is('logout') ||
            $request->is('storage/*');

        if ($allowed) {
            return $next($request);
        }

        // Якщо це API-запит — віддаємо 403 (щоб не тягнув банки напряму)
        if ($request->is('api/*')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        // все інше -> на список поставок
        return redirect('/deliveries');
    }
}

