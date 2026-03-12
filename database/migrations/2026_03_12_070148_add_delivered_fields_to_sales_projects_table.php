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
        Schema::table('sales_projects', function (Blueprint $table) {
            $table->string('delivered_inverter', 255)->nullable()->after('inverter');
            $table->string('delivered_battery', 255)->nullable()->after('battery_qty');
            $table->string('delivered_bms', 255)->nullable()->after('bms');
            $table->string('delivered_panels', 255)->nullable()->after('panel_qty');
        });
    }

    public function down(): void
    {
        Schema::table('sales_projects', function (Blueprint $table) {
            $table->dropColumn(['delivered_inverter', 'delivered_battery', 'delivered_bms', 'delivered_panels']);
        });
    }
};
