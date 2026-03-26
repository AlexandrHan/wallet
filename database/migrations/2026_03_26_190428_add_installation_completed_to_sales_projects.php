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
            $table->dateTime('installation_completed_at')->nullable()->after('construction_status');
            $table->string('installation_completed_by')->nullable()->after('installation_completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('sales_projects', function (Blueprint $table) {
            $table->dropColumn(['installation_completed_at', 'installation_completed_by']);
        });
    }
};
