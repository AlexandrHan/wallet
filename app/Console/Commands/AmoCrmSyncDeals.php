<?php

namespace App\Console\Commands;

use App\Services\AmoCrmService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AmoCrmSyncDeals extends Command
{
    protected $signature = 'amocrm:sync-deals {--limit=250 : Page size (max 250)}';

    protected $description = 'Sync deals from amoCRM to Wallet';

    public function handle(AmoCrmService $amoCrmService): int
    {
        $page = 1;
        $limit = max(1, min((int) $this->option('limit'), 250));

        $totalDeals = 0;
        $created = 0;
        $updated = 0;
        $allSyncedIds = [];

        try {
            do {
                $deals = $amoCrmService->fetchDeals($page, $limit);

                if (empty($deals)) {
                    break;
                }

                $sync = $amoCrmService->syncDeals($deals);
                $totalDeals += (int) ($sync['total'] ?? 0);
                $created += (int) ($sync['created'] ?? 0);
                $updated += (int) ($sync['updated'] ?? 0);
                $allSyncedIds = array_merge($allSyncedIds, $sync['synced_ids'] ?? []);

                $this->info(sprintf(
                    'Page %d: total=%d created=%d updated=%d',
                    $page,
                    (int) ($sync['total'] ?? 0),
                    (int) ($sync['created'] ?? 0),
                    (int) ($sync['updated'] ?? 0)
                ));

                $page++;
            } while (count($deals) === $limit);

            // Second pass: update deals that moved out of project_status_ids.
            $outOfStage = $amoCrmService->syncOutOfStageProjectDeals($allSyncedIds);
            $updated += (int) ($outOfStage['updated'] ?? 0);

            $this->info(sprintf(
                'Done. Synced deals: %d (created: %d, updated: %d, out-of-stage: %d)',
                $totalDeals, $created, $updated - ($outOfStage['updated'] ?? 0), (int) ($outOfStage['updated'] ?? 0)
            ));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::error('amocrm:sync-deals failed', [
                'error' => $e->getMessage(),
            ]);

            $this->error('amoCRM sync failed: '.$e->getMessage());

            try {
                $hlushchenko = \Illuminate\Support\Facades\DB::table('users')
                    ->where('actor', 'hlushchenko')
                    ->first(['id']);
                if ($hlushchenko) {
                    app(\App\Services\NotificationService::class)->send(
                        (int) $hlushchenko->id,
                        '❌ Синк AMO не виконано',
                        'Синхронізація проектів з amoCRM завершилась помилкою. Projects не оновлено. Причина: ' . $e->getMessage(),
                        'system'
                    );
                }
            } catch (\Throwable) {
                // не ламаємо команду якщо notification fail
            }

            return self::FAILURE;
        }
    }
}
