<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_projects', function (Blueprint $table) {
            $table->string('geo_location_link')->nullable()->after('telegram_group_link');
            $table->boolean('has_green_tariff')->default(false)->after('geo_location_link');
        });
    }

    public function down(): void
    {
        Schema::table('sales_projects', function (Blueprint $table) {
            $table->dropColumn(['geo_location_link', 'has_green_tariff']);
        });
    }
};
