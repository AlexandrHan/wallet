<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('service_requests', 'project_id')) {
                $table->unsignedBigInteger('project_id')->nullable()->index();
            }
            if (!Schema::hasColumn('service_requests', 'amo_deal_id')) {
                $table->unsignedBigInteger('amo_deal_id')->nullable()->index();
            }
            if (!Schema::hasColumn('service_requests', 'source')) {
                $table->string('source')->nullable()->index();
            }
        });

        if (Schema::hasColumn('service_requests', 'source')) {
            DB::table('service_requests')
                ->whereNull('source')
                ->whereNull('created_by')
                ->update(['source' => 'google_sheet']);

            DB::table('service_requests')
                ->whereNull('source')
                ->whereNotNull('created_by')
                ->update(['source' => 'manual']);
        }
    }

    public function down(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            if (Schema::hasColumn('service_requests', 'project_id')) {
                $table->dropColumn('project_id');
            }
            if (Schema::hasColumn('service_requests', 'amo_deal_id')) {
                $table->dropColumn('amo_deal_id');
            }
            if (Schema::hasColumn('service_requests', 'source')) {
                $table->dropColumn('source');
            }
        });
    }
};
