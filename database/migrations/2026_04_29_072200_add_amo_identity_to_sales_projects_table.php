<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_projects', function (Blueprint $table) {
            if (!Schema::hasColumn('sales_projects', 'amo_deal_id')) {
                $table->unsignedBigInteger('amo_deal_id')->nullable()->after('source_layer');
            }

            if (!Schema::hasColumn('sales_projects', 'pipeline_id')) {
                $table->unsignedBigInteger('pipeline_id')->nullable()->after('amo_deal_id');
            }

            if (!Schema::hasColumn('sales_projects', 'amo_deal_name')) {
                $table->string('amo_deal_name')->nullable()->after('pipeline_id');
            }

            if (!Schema::hasColumn('sales_projects', 'amo_status_id')) {
                $table->unsignedBigInteger('amo_status_id')->nullable()->after('amo_deal_name');
            }
        });

        Schema::table('sales_projects', function (Blueprint $table) {
            $table->index(['source_layer', 'amo_deal_id'], 'sales_projects_source_layer_amo_deal_id_index');
            $table->index('amo_deal_id', 'sales_projects_amo_deal_id_index');
            $table->index(['pipeline_id', 'amo_status_id'], 'sales_projects_pipeline_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('sales_projects', function (Blueprint $table) {
            $table->dropIndex('sales_projects_source_layer_amo_deal_id_index');
            $table->dropIndex('sales_projects_amo_deal_id_index');
            $table->dropIndex('sales_projects_pipeline_status_index');
        });

        Schema::table('sales_projects', function (Blueprint $table) {
            if (Schema::hasColumn('sales_projects', 'amo_status_id')) {
                $table->dropColumn('amo_status_id');
            }

            if (Schema::hasColumn('sales_projects', 'amo_deal_name')) {
                $table->dropColumn('amo_deal_name');
            }

            if (Schema::hasColumn('sales_projects', 'pipeline_id')) {
                $table->dropColumn('pipeline_id');
            }

            if (Schema::hasColumn('sales_projects', 'amo_deal_id')) {
                $table->dropColumn('amo_deal_id');
            }
        });
    }
};
