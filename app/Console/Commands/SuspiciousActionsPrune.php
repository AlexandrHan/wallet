<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SuspiciousActionsPrune extends Command
{
    protected $signature = 'suspicious-actions:prune {--months=2 : How many months of suspicious notifications to keep}';

    protected $description = 'Delete suspicious action notifications older than the retention period';

    private const TITLES = [
        '⚠️ Зміна фінансової операції',
        '❌ Видалено фінансову операцію',
        '🔄 Змінено валюту авансу',
        '❌ Скасовано аванс',
        '🚫 Аванс скасовано',
        '✏️ Валюту авансу виправлено',
        '❌ Проект видалено',
    ];

    public function handle(): int
    {
        $months = max(1, (int) $this->option('months'));
        $cutoff = now()->subMonthsNoOverflow($months);

        $deleted = DB::table('notifications')
            ->whereIn('title', self::TITLES)
            ->where('created_at', '<', $cutoff)
            ->delete();

        $this->info("Deleted {$deleted} suspicious action notifications older than {$months} months.");

        return self::SUCCESS;
    }
}
