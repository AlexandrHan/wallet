<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Services\ErpNextService;

class ErpSyncCashDaily extends Command
{
    protected $signature = 'erp:sync-cash-daily';
    protected $description = 'Send daily cash summary to ERP';

    public function handle()
{
    $this->info('ErpSyncCashDaily DISABLED');
    return 0;
}



    
    // {
    //     $rows = DB::table('cash_daily_summaries')
    //         ->whereNull('erp_synced_at')
    //         ->orderBy('date')
    //         ->orderBy('wallet_id')
    //         ->get();

    //     if ($rows->isEmpty()) {
    //         $this->info('No cash daily summaries to sync');
    //         return 0;
    //     }

    //     $erp = app(ErpNextService::class);
    //     $synced = 0;

    //     foreach ($rows as $r) {
    //         try {
    //             // 🔹 ВАЖЛИВО: ми відправляємо ДЕНЬ, А НЕ ОПЕРАЦІЇ
    //             $amount = (float)$r->income - (float)$r->expense;

    //             $erp->syncBankBalanceDelta(
    //                 company: 'sg_group', // кеш у SGH
    //                 bankAccountName: $r->currency.' '.($r->owner === 'kolisnyk' ? 'Колісник' : 'Глущенко').' КЕШ - SGH',
    //                 currency: $r->currency,
    //                 amount: $amount,
    //                 postingDate: $r->date
    //             );


    //             DB::table('cash_daily_summaries')
    //                 ->where('id', $r->id)
    //                 ->update([
    //                     'erp_synced_at' => now(),
    //                     'updated_at'    => now(),
    //                 ]);

    //             $synced++;
    //             $this->info("Synced: {$r->wallet_name} ({$r->date})");

    //             } catch (\Throwable $e) {

    //                 $this->error("Failed: {$r->wallet_name} - ".$e->getMessage());

    //             }



    //     }

    //     $this->info("ERP cash daily synced: {$synced} rows");
    //     return 0;
    // }
}
