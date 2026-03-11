<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fx_rates', function (Blueprint $table) {
            $table->id();
            $table->string('currency', 3)->unique();
            $table->decimal('buy', 12, 4);
            $table->decimal('sell', 12, 4);
            $table->string('source', 50)->default('manual');
            $table->timestamps();
        });

        DB::table('fx_rates')->insert([
            [
                'currency' => 'USD',
                'buy' => 40.0000,
                'sell' => 41.0000,
                'source' => 'seed',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'currency' => 'EUR',
                'buy' => 43.0000,
                'sell' => 44.0000,
                'source' => 'seed',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('fx_rates');
    }
};
