<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OnlyReclamations
{
    public function handle(Request $request, Closure $next): Response
    {
        $u = $request->user();

        // якщо не sunfix -> пускаємо як було
        if (!$u || $u->role !== 'sunfix') {
            return $next($request);
        }

        // sunfix: дозволяємо тільки маршрути reclamations + logout + storage (для фото)
        $allowed =
            $request->is('reclamations*') ||
            $request->is('logout') ||
            $request->is('storage/*');

        if ($allowed) {
            return $next($request);
        }

        // все інше: ведемо в рекламації
        return redirect()->route('reclamations.index');
    }
}
