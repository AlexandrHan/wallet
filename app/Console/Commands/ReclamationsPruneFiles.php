<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use App\Models\ReclamationLog;
use App\Models\ReclamationStep;

class ReclamationsPruneFiles extends Command
{
    protected $signature = 'reclamations:prune-files';
    protected $description = 'Delete reclamation uploaded files older than 7 days';

    public function handle(): int
    {
        $cutoff = now()->subDays(7);

        $logs = ReclamationLog::where('action', 'file_upload')
            ->where('created_at', '<', $cutoff)
            ->get();

        $deleted = 0;

        foreach ($logs as $log) {
            $path = $log->payload['path'] ?? null;
            if (!$path) continue;

            // видаляємо файл
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
                $deleted++;
            }

            // прибираємо path з files[] у step
            if ($log->step_key) {
                $step = ReclamationStep::where('reclamation_id', $log->reclamation_id)
                    ->where('step_key', $log->step_key)
                    ->first();

                if ($step && is_array($step->files)) {
                    $step->files = array_values(array_filter($step->files, fn($p) => $p !== $path));
                    $step->save();
                }
            }

            // щоб не видаляти знову: змінюємо action
            $log->action = 'file_pruned';
            $log->save();
        }

        $this->info("Deleted: {$deleted}");
        return self::SUCCESS;
    }
}
