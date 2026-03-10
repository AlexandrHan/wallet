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
    Schema::create('supplier_delivery_items', function (Blueprint $table) {
        $table->id();

        $table->foreignId('delivery_id')
              ->constrained('supplier_deliveries')
              ->cascadeOnDelete();

        $table->foreignId('product_id')
              ->constrained()
              ->cascadeOnDelete();

        // що заявив постачальник
        $table->integer('qty_declared');

        // що реально прийняли
        $table->integer('qty_accepted')->nullable();

        // ціна в цій конкретній партії
        $table->decimal('supplier_price', 12, 2);

        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_delivery_items');
    }
};
