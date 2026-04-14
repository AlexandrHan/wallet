<?php

namespace App\Console\Commands;

use App\Services\AmoCrmService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ONE-TIME script: create missing advance cash_transfers for finance/complectation
 * projects that are currently on active AMO finance stages (AMO_FINANCE_STAGE_IDS).
 *
 * Source of prepayment : AMO API field "Предоплата, $" (field_id 107031)
 * ERP paid sum         : SUM(cash_transfers.usd_amount) WHERE project_id AND status='accepted'
 * Action               : if (amo_prepayment > erp_paid) → insert cash_transfer for the difference
 *
 * Idempotent: safe to run multiple times.
 */
class SyncAmoPrepayments extends Command
{
    protected $signature = 'amo:sync-prepayments
                            {--dry-run : Preview changes without writing to DB}
                            {--rollback : Delete all previously synced [amo_sync] transfers}';

    protected $description = '[One-time] Sync missing advance payments from AmoCRM finance-stage deals';

    private int $processed           = 0;
    private int $created             = 0;
    private int $skippedNoPrepayment = 0;
    private int $skippedNoMatch      = 0;
    private int $skippedAlreadyPaid  = 0;

    public function handle(AmoCrmService $amo): int
    {
        // ── Rollback mode ──────────────────────────────────────────────────────
        if ($this->option('rollback')) {
            $count = DB::table('cash_transfers')
                ->where('comment', 'LIKE', '%[amo_sync]%')
                ->count();

            if ($count === 0) {
                $this->info('No [amo_sync] transfers found — nothing to rollback.');
                return self::SUCCESS;
            }

            $this->warn("About to DELETE {$count} cash_transfers with comment LIKE '%[amo_sync]%'");
            if (!$this->confirm('Proceed?')) {
                $this->info('Aborted.');
                return self::SUCCESS;
            }

            DB::table('cash_transfers')
                ->where('comment', 'LIKE', '%[amo_sync]%')
                ->delete();

            $this->info("Deleted {$count} transfers.");
            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('DRY RUN — no changes will be saved');
        }

        // ── 1. Fetch deals from FINANCE pipeline (AMO_FINANCE_STAGE_IDS) ──────
        $this->info('Fetching deals from AmoCRM (finance stages)…');
        $deals = $this->fetchFinanceDeals($amo);
        $this->info('Fetched ' . count($deals) . ' deals');

        if (empty($deals)) {
            $this->warn('No deals returned — check AMO_FINANCE_STAGE_IDS and token.');
            return self::FAILURE;
        }

        // ── 2. Build amo_deal_id → wallet_project_id map (finance pipeline) ───
        $dealMap = DB::table('amo_complectation_projects')
            ->whereNotNull('wallet_project_id')
            ->pluck('wallet_project_id', 'amo_deal_id')
            ->all();

        $this->info('Finance deal map entries: ' . count($dealMap));

        // ── 3. Load accepted transfer sums per project (single query) ──────────
        $paidByProject = DB::table('cash_transfers')
            ->where('status', 'accepted')
            ->whereNotNull('project_id')
            ->selectRaw('project_id, COALESCE(SUM(usd_amount), 0) as total_paid')
            ->groupBy('project_id')
            ->pluck('total_paid', 'project_id')
            ->map(fn ($v) => round((float) $v, 2))
            ->all();

        // ── 4. Process each deal ───────────────────────────────────────────────
        foreach ($deals as $deal) {
            $this->processDeal($deal, $dealMap, $paidByProject, $dryRun);
        }

        $this->printSummary($dryRun);

        return self::SUCCESS;
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function processDeal(
        array $deal,
        array $dealMap,
        array &$paidByProject,
        bool  $dryRun
    ): void {
        $this->processed++;
        $amoDealId = (int) ($deal['id'] ?? 0);

        // ── a) Extract prepayment from AMO custom field 107031 ────────────────
        $amoPrepayment = $this->extractPrepayment($deal);

        if ($amoPrepayment === null || $amoPrepayment <= 0) {
            $this->skippedNoPrepayment++;
            Log::info('amo:sync-prepayments skip — no prepayment', ['amo_deal_id' => $amoDealId]);
            $this->line("  [SKIP no prepayment] amo#{$amoDealId} {$deal['name']}");
            return;
        }

        // ── b) Match to ERP project via complectation deal map ────────────────
        $projectId = $dealMap[$amoDealId] ?? null;

        if (!$projectId) {
            $this->skippedNoMatch++;
            Log::warning('amo:sync-prepayments skip — no ERP match', ['amo_deal_id' => $amoDealId]);
            $this->line("  [SKIP no ERP match] amo#{$amoDealId} {$deal['name']}");
            return;
        }

        $project = DB::table('sales_projects')
            ->where('id', $projectId)
            ->whereNull('cancelled_at')
            ->first(['id', 'client_name', 'currency']);

        if (!$project) {
            $this->skippedNoMatch++;
            Log::warning('amo:sync-prepayments skip — ERP project missing or cancelled', [
                'amo_deal_id' => $amoDealId,
                'project_id'  => $projectId,
            ]);
            $this->line("  [SKIP cancelled/missing] amo#{$amoDealId} → erp#{$projectId}");
            return;
        }

        // ── c) Compare ────────────────────────────────────────────────────────
        $erpPaid = $paidByProject[$projectId] ?? 0.0;
        $missing  = round($amoPrepayment - $erpPaid, 2);

        if ($missing <= 0) {
            $this->skippedAlreadyPaid++;
            $this->line(
                "  [OK]     amo#{$amoDealId} erp#{$projectId} {$project->client_name}"
                . " — amo={$amoPrepayment} paid={$erpPaid}"
            );
            return;
        }

        // ── d) Create transfer ────────────────────────────────────────────────
        $this->line(
            "  [CREATE] amo#{$amoDealId} erp#{$projectId} {$project->client_name}"
            . " — amo={$amoPrepayment} paid={$erpPaid} → +{$missing} {$project->currency}"
        );

        if (!$dryRun) {
            $this->createTransfer($projectId, $missing, $project->currency);
            $paidByProject[$projectId] = round($erpPaid + $missing, 2);
        }

        $this->created++;
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function fetchFinanceDeals(AmoCrmService $amo): array
    {
        $all  = [];
        $page = 1;

        do {
            try {
                $batch = $amo->fetchComplectationDeals($page);
            } catch (\Throwable $e) {
                Log::error('amo:sync-prepayments fetch error', ['page' => $page, 'error' => $e->getMessage()]);
                $this->warn("AMO fetch error on page {$page}: " . $e->getMessage());
                break;
            }

            $all  = array_merge($all, $batch);
            $page++;
        } while (count($batch) === 250 && $page <= 20);

        return $all;
    }

    private function extractPrepayment(array $deal): ?float
    {
        foreach ((array) ($deal['custom_fields_values'] ?? []) as $field) {
            if ((int) ($field['field_id'] ?? 0) === 107031) {
                $raw = $field['values'][0]['value'] ?? null;
                if ($raw !== null) {
                    $num = round((float) preg_replace('/[^\d.]/', '', (string) $raw), 2);
                    return $num > 0 ? $num : null;
                }
            }
        }
        return null;
    }

    private function createTransfer(int $projectId, float $amount, string $currency): void
    {
        DB::transaction(function () use ($projectId, $amount, $currency) {
            $now = now();
            DB::table('cash_transfers')->insert([
                'project_id'     => $projectId,
                'from_wallet_id' => null,
                'to_wallet_id'   => null,
                'amount'         => $amount,
                'usd_amount'     => $amount,
                'currency'       => $currency,
                'exchange_rate'  => null,
                'status'         => 'accepted',
                'transfer_type'  => 'owner',
                'accepted_at'    => $now,
                'created_by'     => 18,
                'comment'        => 'Synced from AmoCRM prepayment [amo_sync]',
                'created_at'     => $now,
                'updated_at'     => $now,
            ]);
        });

        Log::info('amo:sync-prepayments created transfer', [
            'project_id' => $projectId,
            'amount'     => $amount,
            'currency'   => $currency,
        ]);
    }

    private function printSummary(bool $dryRun): void
    {
        $this->newLine();
        $this->info('─── Summary ' . ($dryRun ? '(DRY RUN) ' : '') . str_repeat('─', 30));
        $this->info("  Processed:               {$this->processed}");
        $this->info("  Payments created:        {$this->created}");
        $this->info("  Already paid (skipped):  {$this->skippedAlreadyPaid}");
        $this->info("  No prepayment (skipped): {$this->skippedNoPrepayment}");
        $this->info("  No ERP match (skipped):  {$this->skippedNoMatch}");

        if ($dryRun && $this->created > 0) {
            $this->newLine();
            $this->warn("  Run without --dry-run to apply {$this->created} payment(s)");
        }
    }
}
