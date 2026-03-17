<?php

namespace App\Console\Commands;

use App\Services\ZippyStockService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ZippySyncStock extends Command
{
    protected $signature = 'zippy:sync-stock';

    protected $description = 'Fetch stock from Zippy CRM and sync into solarglass_stock';

    public function handle(ZippyStockService $service): int
    {
        try {
            $result = $service->sync();

            $this->info('Stock sync completed');
            $this->line('updated:  ' . $result['updated']);
            $this->line('created:  ' . $result['created']);
            if ($result['skipped'] > 0) {
                $this->line('skipped:  ' . $result['skipped']);
            }
            $this->line('duration: ' . $result['duration'] . 's');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::error('zippy:sync-stock failed', ['error' => $e->getMessage()]);
            $this->error('Sync failed: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
