<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salary_rules', function (Blueprint $table) {
            $table->decimal('piecework_unit_rate', 12, 2)->nullable()->after('fixed_amount');
            $table->decimal('foreman_bonus', 12, 2)->nullable()->after('piecework_unit_rate');
        });
    }

    public function down(): void
    {
        Schema::table('salary_rules', function (Blueprint $table) {
            $table->dropColumn(['piecework_unit_rate', 'foreman_bonus']);
        });
    }
};
