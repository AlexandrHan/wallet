<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class BankDailyBalanceSnapshot extends Command
{
    protected $signature = 'bank:daily-balance-snapshot';
    protected $description = 'Take daily snapshot of main bank balances';

    public function handle()
    {
        $date = now()->toDateString();

        $endpoints = [
            'solar_engineering' => '/api/bank/accounts',
            'sg_group' => '/api/bank/accounts-sggroup',
        ];

        foreach ($endpoints as $company => $endpoint) {

            $response = Http::get(config('app.url').$endpoint);

            if (!$response->successful()) {
                $this->error("API fail $company");
                continue;
            }

            $accounts = collect($response->json());
            $acc = $accounts->firstWhere('currency', 'UAH');

            if (!$acc) {
                $this->error("No UAH account for $company");
                continue;
            }

            $exists = DB::table('bank_daily_balances')
                ->where('date', $date)
                ->where('company', $company)
                ->exists();

            if ($exists) {
                DB::table('bank_daily_balances')
                    ->where('date', $date)
                    ->where('company', $company)
                    ->update([
                        'balance' => $acc['balance'],
                        'updated_at' => now()
                    ]);

                $this->info("Updated $company balance: " . $acc['balance']);
            } else {
                DB::table('bank_daily_balances')->insert([
                    'date' => $date,
                    'company' => $company,
                    'bank' => 'ukrgasbank',
                    'iban' => $acc['iban'],
                    'currency' => $acc['currency'],
                    'balance' => $acc['balance'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $this->info("Saved $company balance: " . $acc['balance']);
            }
        }

        return self::SUCCESS;
    }
}
