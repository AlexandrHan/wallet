<?php

namespace App\Console\Commands;

use App\Services\AmoCrmService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AmoCrmCancelDeletedDeals extends Command
{
    protected $signature = 'amocrm:cancel-deleted-deals';

    protected $description = 'Cancel ERP projects whose amoCRM deals have been deleted';

    public function handle(AmoCrmService $amoCrmService): int
    {
        try {
            $result = $amoCrmService->cancelDeletedAmoDeals();

            $this->info(sprintf(
                'Done. Checked: %d deals, cancelled: %d projects.',
                $result['checked'],
                $result['cancelled'],
            ));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::error('amocrm:cancel-deleted-deals failed', ['error' => $e->getMessage()]);
            $this->error('Failed: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
