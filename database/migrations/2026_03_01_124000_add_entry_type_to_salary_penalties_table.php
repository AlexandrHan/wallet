<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salary_penalties', function (Blueprint $table) {
            if (!Schema::hasColumn('salary_penalties', 'entry_type')) {
                $table->string('entry_type', 20)->default('penalty')->after('staff_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('salary_penalties', function (Blueprint $table) {
            if (Schema::hasColumn('salary_penalties', 'entry_type')) {
                $table->dropColumn('entry_type');
            }
        });
    }
};
