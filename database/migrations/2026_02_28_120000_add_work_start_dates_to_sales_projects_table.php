<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_projects', function (Blueprint $table) {
            $table->date('electric_work_start_date')->nullable()->after('has_green_tariff');
            $table->date('panel_work_start_date')->nullable()->after('electric_work_start_date');
        });
    }

    public function down(): void
    {
        Schema::table('sales_projects', function (Blueprint $table) {
            $table->dropColumn([
                'electric_work_start_date',
                'panel_work_start_date',
            ]);
        });
    }
};
