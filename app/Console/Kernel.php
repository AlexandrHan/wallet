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

        // amoCRM deals -> Wallet projects (0:00, 0:30, 1:00 ...)
        $schedule->command('amocrm:sync-deals')
            ->everyThirtyMinutes()
            ->withoutOverlapping();

        // amoCRM deals -> Finance (хв:02 і хв:32) — зміщено на 2хв щоб не конкурувати з sync-deals за SQLite lock
        $schedule->command('amocrm:sync-complectation-projects')
            ->cron('2,32 * * * *')
            ->withoutOverlapping();

        // Скасування проектів з видаленими угодами в amoCRM (щотижня, неділя 02:00)
        $schedule->command('amocrm:cancel-deleted-deals')
            ->weeklyOn(0, '02:00')
            ->withoutOverlapping();

        // 🟡 GOOGLE SHEETS — електрики (кожну годину)
        // (вже зареєстровано в routes/console.php — тут закоментовано щоб не дублювати)
        // $schedule->command('sheets:sync-electricians')
        //     ->hourly()
        //     ->withoutOverlapping();

    }










    

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
    }
}
