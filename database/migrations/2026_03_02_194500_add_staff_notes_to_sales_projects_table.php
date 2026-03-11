<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_projects', function (Blueprint $table) {
            if (!Schema::hasColumn('sales_projects', 'electrician_note')) {
                $table->text('electrician_note')->nullable()->after('electrician');
            }

            if (!Schema::hasColumn('sales_projects', 'installation_team_note')) {
                $table->text('installation_team_note')->nullable()->after('installation_team');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales_projects', function (Blueprint $table) {
            if (Schema::hasColumn('sales_projects', 'electrician_note')) {
                $table->dropColumn('electrician_note');
            }

            if (Schema::hasColumn('sales_projects', 'installation_team_note')) {
                $table->dropColumn('installation_team_note');
            }
        });
    }
};
