<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_projects', function (Blueprint $table) {
            $table->string('telegram_group_link')->nullable()->after('status');
            $table->string('inverter')->nullable()->after('telegram_group_link');
            $table->string('bms')->nullable()->after('inverter');
            $table->string('battery_name')->nullable()->after('bms');
            $table->unsignedInteger('battery_qty')->nullable()->after('battery_name');
            $table->string('panel_name')->nullable()->after('battery_qty');
            $table->unsignedInteger('panel_qty')->nullable()->after('panel_name');
            $table->string('electrician')->nullable()->after('panel_qty');
            $table->string('installation_team')->nullable()->after('electrician');
            $table->text('defects_note')->nullable()->after('installation_team');
            $table->string('defects_photo_path')->nullable()->after('defects_note');
            $table->timestamp('closed_at')->nullable()->after('defects_photo_path');
            $table->unsignedBigInteger('closed_by')->nullable()->after('closed_at');
        });
    }

    public function down(): void
    {
        Schema::table('sales_projects', function (Blueprint $table) {
            $table->dropColumn([
                'telegram_group_link',
                'inverter',
                'bms',
                'battery_name',
                'battery_qty',
                'panel_name',
                'panel_qty',
                'electrician',
                'installation_team',
                'defects_note',
                'defects_photo_path',
                'closed_at',
                'closed_by',
            ]);
        });
    }
};
