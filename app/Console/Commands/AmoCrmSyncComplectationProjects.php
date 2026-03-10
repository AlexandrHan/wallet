<?php

namespace App\Console\Commands;

use App\Services\AmoCrmService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AmoCrmSyncComplectationProjects extends Command
{
    protected $signature = 'amocrm:sync-complectation-projects {--limit=250 : Page size (max 250)}';

    protected $description = 'Sync amoCRM complectation stage deals into separate projects table';

    public function handle(AmoCrmService $amoCrmService): int
    {
        $page = 1;
        $limit = max(1, min((int) $this->option('limit'), 250));

        $totalDeals = 0;
        $created = 0;
        $updated = 0;

        try {
            do {
                $deals = $amoCrmService->fetchComplectationDeals($page, $limit);

                if (empty($deals)) {
                    break;
                }

                $sync = $amoCrmService->syncComplectationDeals($deals);
                $totalDeals += (int) ($sync['total'] ?? 0);
                $created += (int) ($sync['created'] ?? 0);
                $updated += (int) ($sync['updated'] ?? 0);

                $this->info(sprintf(
                    'Page %d: total=%d created=%d updated=%d',
                    $page,
                    (int) ($sync['total'] ?? 0),
                    (int) ($sync['created'] ?? 0),
                    (int) ($sync['updated'] ?? 0)
                ));

                $page++;
            } while (count($deals) === $limit);

            $this->info(sprintf('Done. Synced complectation deals: %d (created: %d, updated: %d)', $totalDeals, $created, $updated));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::error('amocrm:sync-complectation-projects failed', [
                'error' => $e->getMessage(),
            ]);

            $this->error('amoCRM complectation sync failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}

