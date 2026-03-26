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
        Schema::table('salary_accruals', function (Blueprint $table) {
            $table->decimal('paid_usd', 10, 2)->nullable()->after('entry_id');
            $table->decimal('paid_uah', 10, 2)->nullable()->after('paid_usd');
            $table->decimal('paid_rate', 10, 4)->nullable()->after('paid_uah');
        });
    }

    public function down(): void
    {
        Schema::table('salary_accruals', function (Blueprint $table) {
            $table->dropColumn(['paid_usd', 'paid_uah', 'paid_rate']);
        });
    }
};
