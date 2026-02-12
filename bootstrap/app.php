<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'only.owner'       => \App\Http\Middleware\OnlyOwner::class,
            'only.bank.roles'  => \App\Http\Middleware\OnlyBankRoles::class,
            'reclamations.access' => \App\Http\Middleware\ReclamationsAccess::class,
            'only.reclamations'   => \App\Http\Middleware\OnlyReclamations::class, 
            'wallets.access' => \App\Http\Middleware\WalletsAccess::class,
            'only.sunfix.manager' => \App\Http\Middleware\OnlySunfixManager::class,

        ]);
        
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->create();

