<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Services\ErpNextService;

class SyncCashEntries extends Command
{
    protected $signature = 'erp:sync-cash-entries';
    protected $description = 'Sync unsynced cash entries to ERP';

    public function handle()
    {
        $erp = app(ErpNextService::class);

        $entries = DB::table('entries')
            ->join('wallets', 'wallets.id', '=', 'entries.wallet_id')
            ->where('wallets.type', 'cash')
            ->where('entries.synced_to_erp', 0)
            ->select('entries.id')
            ->get();

        foreach ($entries as $e) {
            try {
                $erp->syncEntry($e->id);
                $this->info("Synced entry {$e->id}");
            } catch (\Throwable $ex) {
                $this->error("Failed {$e->id}: ".$ex->getMessage());
            }
        }
    }
}
