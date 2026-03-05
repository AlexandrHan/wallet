<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Services\Bank\UkrgasbankBalanceSyncService;



class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // 🔵 БАНК — кожні 10 хв
        $schedule->call(function () {
            app(\App\Services\Bank\UkrgasbankBalanceSyncService::class)
                ->sync('ukrgasbank', config('services.ukrgasbank.token'));
        })->everyTenMinutes();

        // 🟢 КЕШ — підсумок дня
        // $schedule->command('cash:daily-summary')
        //     ->dailyAt('23:30')
        //     ->withoutOverlapping();

        // 🟢 ВІДПРАВКА КЕШУ В ERP
        // $schedule->command('erp:sync-cash-daily')
        //     ->dailyAt('23:31')
        //     ->withoutOverlapping();

        // 🟣 БАНК В ERP
        $schedule->command('erp:sync-bank-daily')
            ->dailyAt('23:40');

        $schedule->command('erp:sync-cash-entries')->dailyAt('23:00');
        
        $schedule->command('reclamations:prune-files')->dailyAt('03:30');

        // amoCRM deals -> Wallet projects
        $schedule->command('amocrm:sync-deals')
            ->everyFiveMinutes()
            ->withoutOverlapping();

    }










    

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
    }
}
