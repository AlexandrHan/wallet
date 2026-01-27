<?php

namespace App\Console\Commands;

use App\Models\Entry;

class SyncEntriesToErp extends Command
{
    protected $signature = 'erp:sync-daily';
    protected $description = 'Send daily entries to ERP';

    public function handle()
    {
        $date = now()->toDateString();

        $entries = Entry::whereDate('posting_date', $date)
            ->whereNull('erp_synced_at')
            ->get();

        foreach ($entries as $entry) {
            app(\App\Services\ErpNextService::class)->syncEntry($entry);

            $entry->update([
                'erp_synced_at' => now(),
                'erp_sync_date' => $date,
            ]);
        }

        $this->info("ERP synced: {$entries->count()} entries");
    }
}
