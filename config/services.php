<?php

return [

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'ukrgasbank' => [
        'token' => env('UKRGASBANK_TOKEN'),
        'base_url' => 'https://my.ukrgasbank.com',
    ],

    'ukrgasbank_sggroup' => [
        'token' => env('UKRGASBANK_SGGROUP_TOKEN'),
    ],

    'ukrgasbank_solarglass' => [
        'token' => env('UKRGASBANK_SOLARGLASS_TOKEN'),
    ],


    'privatbank' => [
        'token' => env('PRIVATBANK_TOKEN'),
    ],

    'monobank' => [
        'token' => env('MONOBANK_TOKEN'),
    ],

    'fx_agent' => [
        'token' => env('FX_AGENT_TOKEN'),
    ],




    // ✅ ERPNext — ОДИН раз і всередині return
    'erpnext' => [
        'url' => env('ERPNEXT_URL'),
        'key' => env('ERPNEXT_API_KEY'),
        'secret' => env('ERPNEXT_API_SECRET'),
        'company_map' => [
            'sg_group' => 'ТОВ СГ Груп',
            'solar_engineering' => 'ТОВ Солар Інженіринг',
            'kolisnyk' => 'ФОП Колісник', // 👈 нове
        ],

        // 'company' => [
        //     'sg_group' => 'ТОВ СГ Груп',
        //     'solar_engineering' => 'ТОВ Солар Інженіринг',
        // ],

        'company' => 'SG Holding',

        'cost_center' => env('ERPNEXT_COST_CENTER', 'Main - SGH'),

        'expense_account' => env('ERPNEXT_EXPENSE_ACCOUNT', 'Витрати Saldo - SGH'),
        'income_account' => env('ERPNEXT_INCOME_ACCOUNT', 'Доходи Saldo - SGH'),

        'account_suffix' => env('ERPNEXT_ACCOUNT_SUFFIX', ' - SGH'),

        'fx' => [
            'uah' => env('FX_UAH', 1),
            'usd' => env('FX_USD', 40),
            'eur' => env('FX_EUR', 43),
        ],
    ],

    'automation' => [
        'url' => env('AUTOMATION_URL', ''),
        'token' => env('AUTOMATION_TOKEN'),
    ],

    'amocrm' => [
        'domain' => env('AMO_DOMAIN'),
        'client_id' => env('AMO_CLIENT_ID'),
        'client_secret' => env('AMO_CLIENT_SECRET'),
        'redirect_uri' => env('AMO_REDIRECT_URI'),
        'refresh_token' => env('AMO_REFRESH_TOKEN'),
        'authorization_code' => env('AMO_AUTHORIZATION_CODE'),
        'project_status_id' => (int) env('AMO_PROJECT_STATUS_ID', 29352208),
        'won_status_id' => (int) env('AMO_WON_STATUS_ID', 142),
        'webhook_secret' => env('AMO_WEBHOOK_SECRET'),
    ],

];
