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
})->everyTenMinutes();

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

// 🔔 Перевірка залишку Сонячного кабелю — щодня о 10:00 (пн-пт)
// Рахуємо SUM по всіх позиціях категорії (item_name містить "кабел" + "соняч")
// ID00238 (старий) + ID00366/ID00367 (нові) — всі м
Schedule::call(function () {
    $qty = (int) \Illuminate\Support\Facades\DB::table('solarglass_stock')
        ->where('item_name', 'like', '%Кабел%')
        ->where('item_name', 'like', '%оняч%')
        ->sum('qty');

    if ($qty < 1000) {
        app(\App\Services\NotificationService::class)->sendToRole(
            'owner',
            '⚠️ Сонячний кабель закінчується',
            "На складі залишилось {$qty} м Сонячного кабелю. Потрібно замовити.",
            'system'
        );
    }
})->dailyAt('10:00')->weekdays();

// 🔔 Перевірка залишку Профілю монтажного — щодня о 10:00 (пн-пт)
Schedule::call(function () {
    // qty в штуках (1 шт = 6 м), поріг < 400 м = < 67 шт
    $qtyPcs = (int) \Illuminate\Support\Facades\DB::table('solarglass_stock')
        ->where('item_code', 'ID00331')
        ->value('qty');

    $qtyMeters = $qtyPcs * 6;

    if ($qtyMeters < 400) {
        app(\App\Services\NotificationService::class)->sendToRole(
            'owner',
            '⚠️ Профіль монтажний закінчується',
            "На складі залишилось {$qtyMeters} м Профілю монтажного ({$qtyPcs} шт). Потрібно замовити.",
            'system'
        );
    }
})->dailyAt('10:00')->weekdays();
