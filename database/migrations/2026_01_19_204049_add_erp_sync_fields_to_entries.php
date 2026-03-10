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
            Schema::table('entries', function (Blueprint $table) {
                $table->timestamp('erp_synced_at')->nullable()->after('updated_at');
                $table->date('erp_sync_date')->nullable()->after('erp_synced_at')->index();
            });
        }

        public function down(): void
        {
            Schema::table('entries', function (Blueprint $table) {
                $table->dropColumn(['erp_synced_at', 'erp_sync_date']);
            });
        }

};
