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
        Schema::create('cash_daily_summaries', function (Blueprint $table) {
            $table->id();

            $table->date('date'); // день, за який рахуємо

            $table->unsignedBigInteger('wallet_id');
            $table->string('wallet_name');
            $table->string('currency', 3);
            $table->string('owner');

            $table->decimal('income', 14, 2)->default(0);
            $table->decimal('expense', 14, 2)->default(0);

            $table->timestamps();

            $table->unique(['date', 'wallet_id']);
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_daily_summaries');
    }
};
