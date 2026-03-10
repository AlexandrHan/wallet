<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_projects', function (Blueprint $table) {
            if (!Schema::hasColumn('sales_projects', 'electric_work_days')) {
                $table->unsignedSmallInteger('electric_work_days')->default(1)->after('electric_work_start_date');
            }

            if (!Schema::hasColumn('sales_projects', 'panel_work_days')) {
                $table->unsignedSmallInteger('panel_work_days')->default(1)->after('panel_work_start_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales_projects', function (Blueprint $table) {
            if (Schema::hasColumn('sales_projects', 'electric_work_days')) {
                $table->dropColumn('electric_work_days');
            }

            if (Schema::hasColumn('sales_projects', 'panel_work_days')) {
                $table->dropColumn('panel_work_days');
            }
        });
    }
};
