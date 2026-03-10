<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BankHealthCheck extends Command
{
    protected $signature = 'bank:health-check';
    protected $description = 'Check if bank balances are updating';

    public function handle()
    {
        $companies = ['sg_group','solar_engineering'];
        $now = now();

        foreach ($companies as $company) {

            $lastUpdate = DB::table('bank_daily_balances')
                ->where('company', $company)
                ->orderByDesc('updated_at')
                ->value('updated_at');

            if (!$lastUpdate) {
                Log::error("BANK ALERT: No balance record for $company");
                continue;
            }

            $minutes = $lastUpdate->diffInMinutes($now);
    

            if ($minutes > 60) {
                Log::error("BANK ALERT: $company balance not updated for $minutes minutes");
                $this->error("$company stale ($minutes min)");
            } else {
                $this->info("$company OK ($minutes min)");
            }
        }

        return self::SUCCESS;
    }
}
