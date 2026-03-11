<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_projects', function (Blueprint $table) {
            if (!Schema::hasColumn('sales_projects', 'source_layer')) {
                $table->string('source_layer', 32)->default('finance')->after('status');
            }
        });

        DB::table('sales_projects')
            ->whereNull('source_layer')
            ->update(['source_layer' => 'finance']);
    }

    public function down(): void
    {
        Schema::table('sales_projects', function (Blueprint $table) {
            if (Schema::hasColumn('sales_projects', 'source_layer')) {
                $table->dropColumn('source_layer');
            }
        });
    }
};

