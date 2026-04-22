<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('amocrm_deal_map', function (Blueprint $table) {
            $table->string('responsible_name', 255)->nullable()->after('amo_status_id');
        });
    }

    public function down(): void
    {
        Schema::table('amocrm_deal_map', function (Blueprint $table) {
            $table->dropColumn('responsible_name');
        });
    }
};
