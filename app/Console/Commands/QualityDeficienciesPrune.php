<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class QualityDeficienciesPrune extends Command
{
    protected $signature = 'quality:prune-deficiencies';
    protected $description = 'Delete deficiency content (text, photos, voice) from quality checks older than 3 months';

    public function handle(): int
    {
        $cutoff = now()->subMonths(3);

        // Find all quality checks older than 3 months
        $oldCheckIds = DB::table('quality_checks')
            ->where('updated_at', '<', $cutoff)
            ->pluck('id')
            ->all();

        if (empty($oldCheckIds)) {
            $this->info('Nothing to prune.');
            return self::SUCCESS;
        }

        // Find checks that actually have deficiency content
        $checksWithContent = DB::table('quality_checks')
            ->whereIn('id', $oldCheckIds)
            ->where(function ($q) {
                $q->whereNotNull('deficiencies')
                  ->orWhereNotNull('voice_memo_path');
            })
            ->select('id', 'voice_memo_path')
            ->get();

        $contentIds = $checksWithContent->pluck('id')->all();

        // Delete voice memo files from disk
        foreach ($checksWithContent as $check) {
            if ($check->voice_memo_path && Storage::disk('public')->exists($check->voice_memo_path)) {
                Storage::disk('public')->delete($check->voice_memo_path);
            }
        }

        // Delete photo files from disk and records from DB for ALL old checks
        $photos = DB::table('quality_photos')
            ->whereIn('quality_check_id', $oldCheckIds)
            ->select('id', 'file_path')
            ->get();

        foreach ($photos as $photo) {
            if ($photo->file_path && Storage::disk('public')->exists($photo->file_path)) {
                Storage::disk('public')->delete($photo->file_path);
            }
        }

        $deletedPhotos = DB::table('quality_photos')
            ->whereIn('quality_check_id', $oldCheckIds)
            ->delete();

        // Clear deficiency text and voice memo path from quality checks
        $cleared = 0;
        if (!empty($contentIds)) {
            $cleared = DB::table('quality_checks')
                ->whereIn('id', $contentIds)
                ->update([
                    'deficiencies'    => null,
                    'voice_memo_path' => null,
                ]);
        }

        $this->info("Pruned: {$cleared} checks cleared, {$deletedPhotos} photos deleted.");
        return self::SUCCESS;
    }
}
