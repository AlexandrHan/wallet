<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_projects', function (Blueprint $table) {
            if (!Schema::hasColumn('sales_projects', 'lead_manager_user_id')) {
                $table->unsignedBigInteger('lead_manager_user_id')->nullable()->after('created_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales_projects', function (Blueprint $table) {
            if (Schema::hasColumn('sales_projects', 'lead_manager_user_id')) {
                $table->dropColumn('lead_manager_user_id');
            }
        });
    }
};
