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

        Schema::table('amo_complectation_projects', function (Blueprint $table) {
            if (!Schema::hasColumn('amo_complectation_projects', 'pipeline_id')) {
                $table->unsignedBigInteger('pipeline_id')->nullable()->after('total_amount');
            }

            if (!Schema::hasColumn('amo_complectation_projects', 'amo_closed_at')) {
                $table->dateTime('amo_closed_at')->nullable()->after('won_at');
            }
        });

        DB::table('amo_complectation_projects')
            ->orderBy('id')
            ->select(['id', 'raw_payload', 'pipeline_id', 'amo_closed_at'])
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    $payload = json_decode((string) ($row->raw_payload ?? ''), true);
                    if (!is_array($payload)) {
                        continue;
                    }

                    $update = [];

                    $pipelineId = $payload['pipeline_id'] ?? null;
                    if ($row->pipeline_id === null && is_numeric($pipelineId) && (int) $pipelineId > 0) {
                        $update['pipeline_id'] = (int) $pipelineId;
                    }

                    $closedAt = $payload['closed_at'] ?? null;
                    if ($row->amo_closed_at === null && is_numeric($closedAt) && (int) $closedAt > 0) {
                        $update['amo_closed_at'] = Carbon::createFromTimestamp((int) $closedAt)->toDateTimeString();
                    }

                    if ($update !== []) {
                        DB::table('amo_complectation_projects')
                            ->where('id', $row->id)
                            ->update($update);
                    }
                }
            });
    }

    public function down(): void
    {
        if (!Schema::hasTable('amo_complectation_projects')) {
            return;
        }

        if (Schema::hasColumn('amo_complectation_projects', 'pipeline_id')) {
            Schema::table('amo_complectation_projects', function (Blueprint $table) {
                $table->dropColumn('pipeline_id');
            });
        }
    }
};
