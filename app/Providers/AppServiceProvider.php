<?php

namespace App\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // SQLite WAL mode + busy_timeout so concurrent writers retry instead of
        // immediately throwing "database is locked" (amocrm:sync-deals and
        // amocrm:sync-complectation-projects both write sales_projects simultaneously).
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement('PRAGMA journal_mode=WAL;');
            DB::statement('PRAGMA busy_timeout=10000;');
        }
    }
}
