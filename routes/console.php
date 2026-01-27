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