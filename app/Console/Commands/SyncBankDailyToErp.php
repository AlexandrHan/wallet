<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Services\ErpNextService;

class SyncBankDailyToErp extends Command
{
    protected $signature = 'erp:sync-bank-daily';
    protected $description = 'Sync daily bank balance delta to ERP';

    public function handle()
    {
        $date = now()->toDateString();

        $rows = DB::table('bank_daily_balances')
            ->whereDate('date', $date)
            ->whereNull('erp_synced_at')
            ->get();

        if ($rows->isEmpty()) {
            $this->info('No bank balances to sync');
            return;
        }

        $erp = app(ErpNextService::class);

        foreach ($rows as $row) {

            $prev = DB::table('bank_daily_balances')
                ->where('company', $row->company)
                ->where('bank', $row->bank)
                ->where('currency', $row->currency)
                ->where('date', '<', $row->date)
                ->orderByDesc('date')
                ->first();

            $prevBalance = $prev ? (float)$prev->balance : 0;
            $delta = round($row->balance - $prevBalance, 2);

            if ($delta == 0.0) {
                DB::table('bank_daily_balances')
                    ->where('id', $row->id)
                    ->update(['erp_synced_at' => now()]);

                $this->info("{$row->company}: no balance change");
                continue;
            }

            $erp->syncBankBalanceDelta(
                company: $row->company,
                bankAccountName: $this->bankAccountName($row),
                currency: $row->currency,
                amount: $delta,
                postingDate: $row->date
            );

            DB::table('bank_daily_balances')
                ->where('id', $row->id)
                ->update(['erp_synced_at' => now()]);

            $this->info("{$row->company}: synced delta {$delta}");
        }
    }

    protected function bankAccountName($row): string
    {
        return match ($row->company) {
            'solar_engineering' => 'Ukrgasbank UAH - SE',
            'sg_group'          => 'Ukrgasbank UAH - SGG',
            default             => throw new \RuntimeException('Unknown company: '.$row->company),
        };
    }

}
