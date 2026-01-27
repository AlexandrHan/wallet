<?php

namespace App\Services\Bank;

use App\Models\BankAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UkrgasbankBalanceSyncService
{
    public function sync(string $bankCode, string $token): int
    {
        Log::info('UGB BALANCE SYNC START', [
            'bank_code' => $bankCode,
        ]);

        $response = Http::withToken($token)
            ->get('https://my.ukrgasbank.com/api/v3/accounts');

        if (!$response->ok()) {
            Log::error('UGB API ERROR', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            return 0;
        }

        $data = $response->json();
        $rows = $data['rows'] ?? [];

        Log::info('UGB ACCOUNTS RECEIVED', [
            'count' => count($rows),
        ]);

        if (empty($rows)) {
            return 0;
        }

        $updated = 0;

        foreach ($rows as $a) {

            $balance =
                $a['balance']['balance']
                ?? $a['balance']['closingBalance']
                ?? 0;

            BankAccount::updateOrCreate(
                [
                    'bank_code' => $bankCode,
                    'iban'      => $a['iban'] ?? null,
                ],
                [
                    'name'            => $a['name'] ?? 'Ukrgasbank account',
                    'currency'        => $a['currency'] ?? 'UAH',
                    'balance'         => (float) $balance,
                    'balance_at'      => now(),
                    'is_active'       => 1,
                ]
            );

            Log::info('UGB ACCOUNT UPDATED', [
                'iban'    => $a['iban'] ?? null,
                'balance' => $balance,
            ]);

            $updated++;
        }

        Log::info('UGB BALANCE SYNC DONE', [
            'bank_code' => $bankCode,
            'updated'   => $updated,
        ]);

        return $updated;
    }
}
