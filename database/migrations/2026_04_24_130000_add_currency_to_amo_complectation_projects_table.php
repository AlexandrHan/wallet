<?php

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

        if (!Schema::hasColumn('amo_complectation_projects', 'currency')) {
            Schema::table('amo_complectation_projects', function (Blueprint $table) {
                $table->string('currency', 3)->nullable()->after('pipeline_id');
            });
        }

        $pipelines = (array) config('services.amocrm.salary_pipelines', []);

        DB::table('amo_complectation_projects')
            ->whereNull('currency')
            ->orderBy('id')
            ->select(['id', 'pipeline_id', 'raw_payload'])
            ->chunkById(200, function ($rows) use ($pipelines) {
                foreach ($rows as $row) {
                    $payload = json_decode((string) ($row->raw_payload ?? ''), true);
                    $rawCurrency = is_array($payload) ? strtoupper(trim((string) ($payload['currency'] ?? ''))) : '';
                    $pipelineCurrency = strtoupper(trim((string) ($pipelines[(int) $row->pipeline_id]['currency'] ?? 'USD')));
                    $currency = in_array($rawCurrency, ['UAH', 'USD', 'EUR'], true)
                        ? $rawCurrency
                        : (in_array($pipelineCurrency, ['UAH', 'USD', 'EUR'], true) ? $pipelineCurrency : 'USD');

                    DB::table('amo_complectation_projects')
                        ->where('id', $row->id)
                        ->update(['currency' => $currency]);
                }
            });
    }

    public function down(): void
    {
        if (!Schema::hasTable('amo_complectation_projects')) {
            return;
        }

        if (Schema::hasColumn('amo_complectation_projects', 'currency')) {
            Schema::table('amo_complectation_projects', function (Blueprint $table) {
                $table->dropColumn('currency');
            });
        }
    }
};
