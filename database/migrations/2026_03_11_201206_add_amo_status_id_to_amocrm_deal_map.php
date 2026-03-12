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
        Schema::table('amocrm_deal_map', function (Blueprint $table) {
            $table->unsignedBigInteger('amo_status_id')->nullable()->after('wallet_project_id');
        });
    }

    public function down(): void
    {
        Schema::table('amocrm_deal_map', function (Blueprint $table) {
            $table->dropColumn('amo_status_id');
        });
    }
};
