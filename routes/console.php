<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Services\Bank\UkrgasbankBalanceSyncService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


Schedule::call(function () {
    app(\App\Services\Bank\UkrgasbankBalanceSyncService::class)
        ->sync(
            'ukrgasbank_sggroup',
            config('services.ukrgasbank.token')
        );
})->everyMinute();

Schedule::command('amocrm:sync-deals')
    ->everyThirtyMinutes()
    ->withoutOverlapping();

Schedule::command('amocrm:sync-complectation-projects')
    ->everyThirtyMinutes()
    ->withoutOverlapping();

Schedule::command('zippy:sync-stock')
    ->everyThirtyMinutes()
    ->withoutOverlapping();

// 🟡 GOOGLE SHEETS — електрики (кожну годину)
Schedule::command('sheets:sync-electricians')
    ->hourly()
    ->withoutOverlapping();

// 🟠 GOOGLE SHEETS — монтажники (кожну годину)
Schedule::command('sheets:sync-installers')
    ->hourly()
    ->withoutOverlapping();

// 🗑 Очищення даних про недоліки старших 3 місяців
Schedule::command('quality:prune-deficiencies')
    ->dailyAt('03:00');
