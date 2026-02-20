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
    Schema::table('cash_transfers', function (Blueprint $table) {
        $table->decimal('exchange_rate', 12, 6)->nullable()->after('currency');
        $table->decimal('usd_amount', 15, 2)->nullable()->after('exchange_rate');
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cash_transfers', function (Blueprint $table) {
            //
        });
    }
};
