<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('battery_norm_rules', function (Blueprint $table) {
            $table->id();
            $table->string('match_text');   // підрядок для пошуку (case-insensitive)
            $table->string('output_name');  // нормалізована назва
            $table->integer('sort_order')->default(0); // вищий = перевіряється першим
            $table->timestamps();
        });

        // Початкові правила
        DB::table('battery_norm_rules')->insert([
            ['match_text' => 'LV D53',   'output_name' => 'T-BAT LV D53',            'sort_order' => 10, 'created_at' => now(), 'updated_at' => now()],
            ['match_text' => 'HV S3.6',  'output_name' => 'T-BAT SYS HV S3.6',       'sort_order' => 10, 'created_at' => now(), 'updated_at' => now()],
            ['match_text' => 'HS510',    'output_name' => 'TB-HS51O (5.1 кВт*год)',   'sort_order' => 10, 'created_at' => now(), 'updated_at' => now()],
            ['match_text' => 'HS51O',    'output_name' => 'TB-HS51O (5.1 кВт*год)',   'sort_order' => 10, 'created_at' => now(), 'updated_at' => now()],
            ['match_text' => 'H 3.0',    'output_name' => 'T-BAT H 3.0',              'sort_order' => 10, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('battery_norm_rules');
    }
};
