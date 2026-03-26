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
        'ukrgasbank_solarglass' => [
        'token' => env('UKRGASBANK_SOLARGLASS_TOKEN'),
    ],


    'privatbank' => [
        'token' => env('PRIVATBANK_TOKEN'),
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

    'zippy' => [
        'base_url'    => env('ZIPPY_BASE_URL', ''),
        'api_key'     => env('ZIPPY_API_KEY', ''),        // статичний Bearer token (якщо є)
        'auth_path'   => env('ZIPPY_AUTH_PATH', ''),      // шлях для отримання JWT (напр. api/common)
        'login'       => env('ZIPPY_API_LOGIN', ''),
        'password'    => env('ZIPPY_API_PASSWORD', ''),
        'stock_path'  => env('ZIPPY_STOCK_PATH', 'api/stock'),
        'stock_method'=> env('ZIPPY_STOCK_METHOD', 'get'),// метод JSON-RPC для отримання товарів
        'timeout'     => (int) env('ZIPPY_TIMEOUT', 30),
    ],

    'automation' => [
        'url' => env('AUTOMATION_URL', ''),
        'token' => env('AUTOMATION_TOKEN'),
    ],

    'google_sheets' => [
        'spreadsheet_id'      => env('GOOGLE_SHEETS_SPREADSHEET_ID', ''),
        'service_account_path' => env(
            'GOOGLE_SERVICE_ACCOUNT_PATH',
            storage_path('app/private/google-service-account.json')
        ),
    ],

    'openclaw' => [
        'url'     => env('OPENCLAW_URL', 'http://localhost:9000'),
        'timeout' => (int) env('OPENCLAW_TIMEOUT', 130),
    ],

    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY', ''),
    ],

    'firebase' => [
        'credentials'        => env('FIREBASE_CREDENTIALS', ''),
        'project_id'         => env('FIREBASE_PROJECT_ID', ''),
        'server_key'         => env('FIREBASE_SERVER_KEY', ''),
        'api_key'            => env('FIREBASE_API_KEY', ''),
        'auth_domain'        => env('FIREBASE_AUTH_DOMAIN', ''),
        'messaging_sender_id'=> env('FIREBASE_MESSAGING_SENDER_ID', ''),
        'app_id'             => env('FIREBASE_APP_ID', ''),
        'vapid_key'          => env('FIREBASE_VAPID_KEY', ''),
    ],

    'ollama' => [
        'url'   => env('OLLAMA_URL', 'http://localhost:11434'),
        'model' => env('OLLAMA_MODEL', 'mistral'),
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
        'project_status_ids' => array_map('intval', array_filter(explode(',', env('AMO_PROJECT_STATUS_IDS', '69586234,38556550,69593822,69593826,69593830')))),
        // Exact AmoCRM stage IDs shown in finance (Проавансовані/Оплачені).
        // Stages: частично оплатил → Комплектація → Очікування доставки →
        // Заплановане будівництво → Монтаж → Електрична частина →
        // Здача проекту → Остаточна оплата (49782427).
        'finance_stage_ids' => array_map('intval', array_filter(explode(',', env('AMO_FINANCE_STAGE_IDS', '38556547,69586234,38556550,69593822,69593826,69593830,69593834,49782427')))),
    ],

];
