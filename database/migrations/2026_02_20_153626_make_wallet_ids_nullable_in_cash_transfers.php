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
        $table->integer('from_wallet_id')->nullable()->change();
        $table->integer('to_wallet_id')->nullable()->change();
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
