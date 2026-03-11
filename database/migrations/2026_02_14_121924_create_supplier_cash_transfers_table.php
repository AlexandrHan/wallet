<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_cash_transfers', function (Blueprint $table) {
            $table->id();

            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('USD');

            $table->unsignedBigInteger('created_by');
            $table->timestamp('sent_at')->nullable();

            $table->unsignedBigInteger('received_by')->nullable();
            $table->timestamp('received_at')->nullable();

            $table->boolean('is_received')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_cash_transfers');
    }
};
