<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_projects', function (Blueprint $table) {
            if (!Schema::hasColumn('sales_projects', 'is_retail')) {
                $table->boolean('is_retail')->default(false)->after('currency');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales_projects', function (Blueprint $table) {
            if (Schema::hasColumn('sales_projects', 'is_retail')) {
                $table->dropColumn('is_retail');
            }
        });
    }
};
