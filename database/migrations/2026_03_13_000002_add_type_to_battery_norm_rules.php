<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('battery_norm_rules', function (Blueprint $table) {
            $table->string('type')->default('battery')->after('id');
        });

        DB::table('battery_norm_rules')->update(['type' => 'battery']);
    }

    public function down(): void
    {
        Schema::table('battery_norm_rules', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
