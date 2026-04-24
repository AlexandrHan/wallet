<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salary_rules', function (Blueprint $table) {
            $table->decimal('commission_percent', 5, 2)->nullable()->after('fixed_amount');
        });
    }

    public function down(): void
    {
        Schema::table('salary_rules', function (Blueprint $table) {
            $table->dropColumn('commission_percent');
        });
    }
};
