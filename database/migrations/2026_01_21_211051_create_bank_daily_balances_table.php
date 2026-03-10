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
        Schema::create('bank_daily_balances', function (Blueprint $table) {
            $table->id();

            $table->date('date');
            $table->string('company');   // solar_engineering, sg_group
            $table->string('bank');      // ukrgasbank
            $table->string('iban')->nullable();
            $table->string('currency', 3);
            $table->decimal('balance', 18, 2);

            $table->timestamp('erp_synced_at')->nullable();

            $table->timestamps();

            $table->unique(['date', 'company', 'bank']);
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_daily_balances');
    }
};
