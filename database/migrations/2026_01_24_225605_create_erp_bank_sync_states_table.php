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
        Schema::create('erp_bank_sync_states', function (Blueprint $table) {
            $table->id();
            $table->string('company');
            $table->string('bank');
            $table->string('iban')->nullable();
            $table->string('currency', 10);
            $table->decimal('last_synced_balance', 15, 2)->default(0);
            $table->date('last_synced_date')->nullable();
            $table->string('last_erp_ref')->nullable();
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('erp_bank_sync_states');
    }
};
