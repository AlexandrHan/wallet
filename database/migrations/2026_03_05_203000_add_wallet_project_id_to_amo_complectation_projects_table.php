<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('amo_complectation_projects', function (Blueprint $table) {
            if (!Schema::hasColumn('amo_complectation_projects', 'wallet_project_id')) {
                $table->unsignedBigInteger('wallet_project_id')->nullable()->unique()->after('amo_deal_id');
                $table->foreign('wallet_project_id')
                    ->references('id')
                    ->on('sales_projects')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('amo_complectation_projects', function (Blueprint $table) {
            if (Schema::hasColumn('amo_complectation_projects', 'wallet_project_id')) {
                $table->dropForeign(['wallet_project_id']);
                $table->dropUnique('amo_complectation_projects_wallet_project_id_unique');
                $table->dropColumn('wallet_project_id');
            }
        });
    }
};

