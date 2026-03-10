<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;



class ErpSyncBankDaily extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:erp-sync-bank-daily';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $companies = ['sg_group','solar_engineering'];

        foreach ($companies as $code) {

            $map = config('services.erpnext.company_map');
            $erpCompany = $map[$code] ?? null;
            if (!$erpCompany) continue;

            $today = DB::table('bank_daily_balances')->where('company',$code)->orderByDesc('date')->value('balance');
            $last = DB::table('erp_bank_sync_states')->where('company',$code)->value('last_synced_balance');

            $delta = round($today - $last, 2);

            if ($delta == 0) {
                $this->info("$code: no change");
                continue;
            }

            $isIncome = $delta > 0;
            $amount = abs($delta);

            $bankAccount = $code == 'sg_group'
                ? 'Ukrgasbank UAH - SGG'
                : 'Ukrgasbank UAH - SE';

            $incomeAcc = $code == 'sg_group'
                ? 'Bank Income - SGG'
                : 'Bank Income - SE';

            $expenseAcc = $code == 'sg_group'
                ? 'Bank Expenses - SGG'
                : 'Bank Expenses - SE';

            $payload = [
                "doctype" => "Journal Entry",
                "voucher_type" => "Journal Entry",
                "company" => $erpCompany,
                "posting_date" => now()->toDateString(),
                "accounts" => [
                    [
                        "account" => $bankAccount,
                        "debit_in_account_currency" => $isIncome ? $amount : 0,
                        "credit_in_account_currency" => $isIncome ? 0 : $amount,
                        'cost_center' => 'Main - SGG',

                    ],
                    [
                        "account" => $isIncome ? $incomeAcc : $expenseAcc,
                        "credit_in_account_currency" => $isIncome ? $amount : 0,
                        "debit_in_account_currency" => $isIncome ? 0 : $amount,

                    ]
                ]
            ];

            $res = Http::withHeaders([
                'Authorization' => 'token '.config('services.erpnext.key').':'.config('services.erpnext.secret')
            ])->post(config('services.erpnext.url').'/api/resource/Journal Entry', $payload);

            if ($res->successful()) {

                $docname = $res->json()['data']['name'];

// 1) GET full doc (як у кеші)
$get = Http::withHeaders([
    'Authorization' => 'token '.config('services.erpnext.key').':'.config('services.erpnext.secret')
])->get(config('services.erpnext.url').'/api/resource/Journal Entry/'.$docname);

if (!$get->successful()) {
    $this->error("GET JE failed: ".$get->body());
    continue;
}

$doc = $get->json('data');

// 2) SUBMIT full doc (як у кеші)
$submit = Http::withHeaders([
    'Authorization' => 'token '.config('services.erpnext.key').':'.config('services.erpnext.secret')
])->post(config('services.erpnext.url').'/api/method/frappe.client.submit', [
    'doc' => $doc
]);

if (!$submit->successful()) {
    $this->error("SUBMIT failed: ".$submit->body());
    continue;
}

// 3) перевіримо docstatus (щоб не гадати)
$check = Http::withHeaders([
    'Authorization' => 'token '.config('services.erpnext.key').':'.config('services.erpnext.secret')
])->get(config('services.erpnext.url').'/api/resource/Journal Entry/'.$docname);

$this->info("JE docstatus=".($check->json('data.docstatus') ?? 'null'));
if (($check->json('data.docstatus') ?? 0) != 1) {
    $this->error("JE still not submitted, stop here");
    continue;
}



                DB::table('erp_bank_sync_states')->where('company',$code)->update([
                    'last_synced_balance' => $today
                ]);

                $this->info($code.' synced');
            } else {
                $this->error($res->body());
            }
        }
    }

}
