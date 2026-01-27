<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CashDailySummary extends Command
{
    protected $signature = 'cash:daily-summary';
    protected $description = 'Generate daily cash income/expense summary';

public function handle()
{
    $today = now()->toDateString();

    $rows = \DB::table('entries')
        ->join('wallets', 'wallets.id', '=', 'entries.wallet_id')
        ->where('wallets.type', 'cash')
        ->where('wallets.is_active', 1)
        ->whereDate('entries.posting_date', $today)
        ->select(
            'wallets.id as wallet_id',
            'wallets.name as wallet_name',
            'wallets.currency',
            'wallets.owner',
            \DB::raw("SUM(CASE WHEN entries.entry_type = 'income' THEN entries.amount ELSE 0 END) as income"),
            \DB::raw("SUM(CASE WHEN entries.entry_type = 'expense' THEN entries.amount ELSE 0 END) as expense")
        )
        ->groupBy(
            'wallets.id',
            'wallets.name',
            'wallets.currency',
            'wallets.owner'
        )
        ->get()
        ->filter(fn ($r) => (float)$r->income !== 0.0 || (float)$r->expense !== 0.0)
        ->values();

    // 🔒 Видаляємо snapshot за цей день (щоб можна було перезапускати)
    \DB::table('cash_daily_summaries')
        ->where('date', $today)
        ->delete();

    foreach ($rows as $r) {
        \DB::table('cash_daily_summaries')->insert([
            'date'        => $today,
            'wallet_id'   => $r->wallet_id,
            'wallet_name' => $r->wallet_name,
            'currency'    => $r->currency,
            'owner'       => $r->owner,
            'income'      => (float)$r->income,
            'expense'     => (float)$r->expense,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    $this->info('Cash daily summary saved: ' . $rows->count() . ' wallets');
}

}
