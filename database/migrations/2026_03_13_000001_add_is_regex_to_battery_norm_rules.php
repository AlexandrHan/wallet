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
            $table->boolean('is_regex')->default(false)->after('match_text');
        });

        // Оновлюємо LV D53 на regex-патерн
        DB::table('battery_norm_rules')
            ->where('match_text', 'LV D53')
            ->update([
                'match_text' => 'lv[\s\-]*d[\s\-]*53',
                'is_regex'   => true,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        Schema::table('battery_norm_rules', function (Blueprint $table) {
            $table->dropColumn('is_regex');
        });
    }
};
