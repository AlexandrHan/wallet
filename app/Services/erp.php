<?php

use Illuminate\Support\Facades\Http;

function erp_ping()
{
    $url = config('services.erpnext.url') . '/api/method/frappe.auth.get_logged_user';

    return Http::withHeaders([
        'Authorization' => 'token '
            . config('services.erpnext.key')
            . ':'
            . config('services.erpnext.secret'),
    ])->get($url);
}


function erp_create_test_journal()
{
    $url = config('services.erpnext.url') . '/api/resource/Journal Entry';

    $payload = [
        'doctype' => 'Journal Entry',
        'voucher_type' => 'Journal Entry',
        'company' => config('services.erpnext.company'),
        'posting_date' => date('Y-m-d'),
        'multi_currency' => 1,
        'remark' => 'TEST FROM LARAVEL',

        'accounts' => [
            [
                'account' => 'EUR Колісник КЕШ - SGH',
                'debit_in_account_currency' => 100,
                'exchange_rate' => 1,
                'cost_center' => config('services.erpnext.cost_center'),
            ],
            [
                'account' => config('services.erpnext.income_account'),
                'credit_in_account_currency' => 100,
                'exchange_rate' => 1,
                'cost_center' => config('services.erpnext.cost_center'),
            ],
        ],
    ];

    return Http::withHeaders([
        'Authorization' => 'token '
            . config('services.erpnext.key')
            . ':'
            . config('services.erpnext.secret'),
        'Content-Type' => 'application/json',
    ])->post($url, $payload);
}
