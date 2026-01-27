<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->string('name');                   // "КЕШ Колісник", "Mono SGH"
            $table->string('currency', 3);            // UAH/USD/EUR
            $table->string('type')->default('cash');  // cash/bank
            $table->string('owner')->nullable();      // "kolisnyk"/"hlushchenko" (поки рядок)
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
