<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('amo_complectation_projects')) {
            return;
        }

        if (!Schema::hasColumn('amo_complectation_projects', 'amo_closed_at')) {
            Schema::table('amo_complectation_projects', function (Blueprint $table) {
                $table->dateTime('amo_closed_at')->nullable()->after('won_at');
            });
        }

        DB::table('amo_complectation_projects')
            ->whereNull('amo_closed_at')
            ->orderBy('id')
            ->select(['id', 'raw_payload'])
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    $payload = json_decode((string) ($row->raw_payload ?? ''), true);
                    if (!is_array($payload)) {
                        continue;
                    }

                    $closedAt = $payload['closed_at'] ?? null;
                    if (!is_numeric($closedAt) || (int) $closedAt <= 0) {
                        continue;
                    }

                    DB::table('amo_complectation_projects')
                        ->where('id', $row->id)
                        ->update([
                            'amo_closed_at' => Carbon::createFromTimestamp((int) $closedAt)->toDateTimeString(),
                        ]);
                }
            });
    }

    public function down(): void
    {
        if (!Schema::hasTable('amo_complectation_projects')) {
            return;
        }

        if (Schema::hasColumn('amo_complectation_projects', 'amo_closed_at')) {
            Schema::table('amo_complectation_projects', function (Blueprint $table) {
                $table->dropColumn('amo_closed_at');
            });
        }
    }
};
