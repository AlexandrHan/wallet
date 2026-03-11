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
        // додаємо тільки те, чого ще нема
        Schema::table('sales', function (Blueprint $table) {
            if (!Schema::hasColumn('sales', 'sold_at')) {
                $table->date('sold_at')->nullable();
            }

            if (!Schema::hasColumn('sales', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            if (Schema::hasColumn('sales', 'created_by')) {
                $table->dropColumn('created_by');
            }

            // sold_at не чіпаємо, бо воно вже було до цієї міграції
            // інакше можемо випадково видалити колонку яку створили раніше
        });
    }


};
