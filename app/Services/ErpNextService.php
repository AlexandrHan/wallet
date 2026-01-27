<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ErpNextService
{
    protected string $baseUrl;
    protected string $apiKey;
    protected string $apiSecret;

    public function __construct()
    {
        $this->baseUrl   = rtrim(config('services.erpnext.url'), '/');
        $this->apiKey    = config('services.erpnext.key');
        $this->apiSecret = config('services.erpnext.secret');
    }

    protected function client()
    {
        return Http::withHeaders([
            'Authorization' => 'token ' . $this->apiKey . ':' . $this->apiSecret,
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json',
        ])->timeout(20);
    }

    /**
     * Sync wallet entry → ERPNext Journal Entry
     * ✔ UAH / USD / EUR
     * ✔ multi-currency
     * ✔ correct submit
     */
    public function syncEntry(int $entryId): array
    {

        $entry = DB::table('entries')->where('id', $entryId)->first();
        if (! $entry) {
            throw new \RuntimeException("Entry {$entryId} not found");
        }

        if ($entry->synced_to_erp) {
            return ['status' => 'already_synced'];
        }

        $wallet = DB::table('wallets')->where('id', $entry->wallet_id)->first();
        if (! $wallet) {
            throw new \RuntimeException("Wallet {$entry->wallet_id} not found");
        }

        // ---- Accounts ----
        $ownerLabel = $wallet->owner === 'kolisnyk'
            ? 'Колісник'
            : 'Глущенко';

        $walletCurrency = strtoupper($wallet->currency); // UAH / USD / EUR

        $cashAccount = "{$walletCurrency} {$ownerLabel} КЕШ - SGH";
        $incomeAccount  = 'Доходи Saldo - SGH';
        $expenseAccount = 'Витрати Saldo - SGH';

        // ---- Company ----
        $company = config('services.erpnext.company');
        $costCenter = config('services.erpnext.cost_center');

        // ---- Amounts ----
        $amountAcc = round((float) $entry->amount, 2);

        $fx = config('services.erpnext.fx') ?? [];
        $rate = (float) ($fx[strtolower($walletCurrency)] ?? 1);

        $amountUah = round($amountAcc * $rate, 2);

        // ---- Journal Entry Lines ----
        if ($entry->entry_type === 'income') {
                $accounts = [
        [
            'account' => $cashAccount,
            'account_currency' => $walletCurrency,
            'exchange_rate' => $rate,
            'debit_in_account_currency' => $amountAcc,
            'debit' => $amountUah,
            'cost_center' => $costCenter,
        ],
        [
            'account' => $incomeAccount,
            'account_currency' => 'UAH',
            'exchange_rate' => 1,
            'credit_in_account_currency' => $amountUah,
            'credit' => $amountUah,
            'cost_center' => $costCenter,
        ],
    ];
} else {
    $accounts = [
        [
            'account' => $expenseAccount,
            'account_currency' => 'UAH',
            'exchange_rate' => 1,
            'debit_in_account_currency' => $amountUah,
            'debit' => $amountUah,
            'cost_center' => $costCenter,
        ],
        [
            'account' => $cashAccount,
            'account_currency' => $walletCurrency,
            'exchange_rate' => $rate,
            'credit_in_account_currency' => $amountAcc,
            'credit' => $amountUah,
            'cost_center' => $costCenter,
        ],
    ];
}

        // ---- Payload ----
        $payload = [
            'doctype' => 'Journal Entry',
            'voucher_type' => 'Journal Entry',
            'company' => $company,
            'posting_date' => $entry->posting_date ?? now()->toDateString(),
            'remark' => $entry->comment ?: "Wallet entry #{$entry->id}",
            'multi_currency' => 1,
            'accounts' => $accounts,
        ];

        Log::info('ERP JE payload', $payload);

        // ---- CREATE ----
        $create = $this->client()->post(
            $this->baseUrl . '/api/resource/Journal%20Entry',
            $payload
        );

if (! $create->successful()) {
    dd($create->status(), $create->body());
}


        $jeName = $create->json('data.name');

        // ---- SUBMIT (THE ONLY CORRECT WAY) ----
        // ---- FETCH FULL DOC BEFORE SUBMIT ----
        $doc = $this->client()->get(
            $this->baseUrl . '/api/resource/Journal%20Entry/' . $jeName
        );

        if (! $doc->successful()) {
            throw new \RuntimeException('Failed to fetch JE before submit');
        }

        // ---- SUBMIT WITH FULL DOC ----
        $submit = $this->client()->post(
            $this->baseUrl . '/api/method/frappe.client.submit',
            [
                'doc' => $doc->json('data'),
            ]
        );

        if (! $submit->successful()) {
            DB::table('entries')->where('id', $entry->id)->update([
                'erp_error' => $submit->body(),
            ]);

            throw new \RuntimeException(
                'ERP JE submit failed: ' . $submit->body()
            );
        }


        if (! $submit->successful()) {
            DB::table('entries')->where('id', $entry->id)->update([
                'erp_error' => $submit->body(),
            ]);

            throw new \RuntimeException(
    'ERP JE submit failed: ' . $submit->body()
);

        }

        // ---- SAVE STATE ----
        DB::table('entries')->where('id', $entry->id)->update([
            'synced_to_erp' => 1,
            'erp_ref' => $jeName,
            'erp_submitted_at' => now(),
            'erp_error' => null,
        ]);

        return [
            'status' => 'submitted',
            'je' => $jeName,
        ];
    }

      // ⬇⬇⬇ ВСТАВИТИ СЮДИ ⬇⬇⬇

    public function syncBankBalanceDelta(
        string $company,
        string $bankAccountName,
        string $currency,
        float $amount,
        string $postingDate
    ): void {

        $isIncome = $amount > 0;
        $amount = abs($amount);

        $incomeAcc = str_contains($company, 'sg_group')
            ? 'Bank Income - SGG'
            : 'Bank Income - SE';

        $expenseAcc = str_contains($company, 'sg_group')
            ? 'Bank Expenses - SGG'
            : 'Bank Expenses - SE';

        $payload = [
            "doctype" => "Journal Entry",
            "voucher_type" => "Journal Entry",
            "company" => config('services.erpnext.company_map')[$company],
            "posting_date" => $postingDate,
            "accounts" => [
                [
                    "account" => $bankAccountName,
                    "debit_in_account_currency" => $isIncome ? $amount : 0,
                    "credit_in_account_currency" => $isIncome ? 0 : $amount,
                    "cost_center" => str_contains($company,'sg_group') ? 'Main - SGG' : 'Main - SE',
                ],
                [
                    "account" => $isIncome ? $incomeAcc : $expenseAcc,
                    "credit_in_account_currency" => $isIncome ? $amount : 0,
                    "debit_in_account_currency" => $isIncome ? 0 : $amount,
                ]
            ]
        ];

        $create = $this->client()->post(
            $this->baseUrl . '/api/resource/Journal%20Entry',
            $payload
        );

        if (! $create->successful()) {
            throw new \RuntimeException($create->body());
        }

        $docname = $create->json('data.name');

        $doc = $this->client()->get(
            $this->baseUrl . '/api/resource/Journal%20Entry/' . $docname
        );

        if (! $doc->successful()) {
            throw new \RuntimeException('Failed to fetch JE before submit');
        }

        $submit = $this->client()->post(
            $this->baseUrl . '/api/method/frappe.client.submit',
            ['doc' => $doc->json('data')]
        );

        if (! $submit->successful()) {
            throw new \RuntimeException('Submit failed: '.$submit->body());
        }
    }

// public function syncCashDailySummary(array $data): void
// {
//     $company = 'SG Holding'; // КЕШ завжди холдинг

//     $ownerLabel = $data['owner'] === 'kolisnyk'
//         ? 'Колісник'
//         : 'Глущенко';

//     $cashAccount = strtoupper($data['currency'])." {$ownerLabel} КЕШ - SGH";

//     $incomeAcc  = 'Доходи Saldo - SGH';
//     $expenseAcc = 'Витрати Saldo - SGH';

//     $amountIncome  = round((float)$data['income'], 2);
//     $amountExpense = round((float)$data['expense'], 2);

//     $accounts = [];

//     if ($amountIncome > 0) {
//         $accounts[] = [
//             'account' => $cashAccount,
//             'debit_in_account_currency' => $amountIncome,
//             'cost_center' => 'Main - SGH'
//         ];
//         $accounts[] = [
//             'account' => $incomeAcc,
//             'credit_in_account_currency' => $amountIncome,
//             'cost_center' => 'Main - SGH'
//         ];
//     }

//     if ($amountExpense > 0) {
//         $accounts[] = [
//             'account' => $expenseAcc,
//             'debit_in_account_currency' => $amountExpense,
//             'cost_center' => 'Main - SGH'
//         ];
//         $accounts[] = [
//             'account' => $cashAccount,
//             'credit_in_account_currency' => $amountExpense,
//             'cost_center' => 'Main - SGH'
//         ];
//     }

//     if (empty($accounts)) {
//         return;
//     }

//     $payload = [
//         'doctype' => 'Journal Entry',
//         'voucher_type' => 'Journal Entry',
//         'company' => $company,
//         'posting_date' => $data['date'],
//         'remark' => "Cash daily summary {$data['wallet']}",
//         'multi_currency' => 1,
//         'accounts' => $accounts
//     ];

//     $create = $this->client()->post(
//         $this->baseUrl . '/api/resource/Journal%20Entry',
//         $payload
//     );

//     if (!$create->successful()) {
//         throw new \RuntimeException($create->body());
//     }

//     $docname = $create->json('data.name');

//     $doc = $this->client()->get(
//         $this->baseUrl . '/api/resource/Journal%20Entry/' . $docname
//     );

//     $submit = $this->client()->post(
//         $this->baseUrl . '/api/method/frappe.client.submit',
//         ['doc' => $doc->json('data')]
//     );

//     if (!$submit->successful()) {
//         throw new \RuntimeException($submit->body());
//     }
// }



}
