<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
public function up(): void
{
    Schema::table('cash_daily_summaries', function (Blueprint $table) {
        $table->timestamp('erp_synced_at')->nullable()->after('expense');
    });
}

public function down(): void
{
    Schema::table('cash_daily_summaries', function (Blueprint $table) {
        $table->dropColumn('erp_synced_at');
    });
}

};
