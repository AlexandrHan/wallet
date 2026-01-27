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
    Schema::create('bank_transactions_raw', function (Blueprint $table) {
        $table->id();

        $table->string('bank_code'); // ukrgasbank
        $table->string('account_iban')->nullable();

        $table->string('external_id')->nullable();
        $table->string('hash')->unique();

        $table->date('operation_date')->nullable();
        $table->integer('dk')->nullable(); // 1 / 2
        $table->decimal('amount', 18, 2)->nullable();
        $table->string('currency', 3)->nullable();

        $table->string('counterparty')->nullable();
        $table->text('purpose')->nullable();

        $table->json('raw')->nullable();

        $table->timestamps();
    });
}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_transactions_raw');
    }
};
