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
    Schema::create('products', function (Blueprint $table) {
        $table->id();

        $table->foreignId('supplier_id')
              ->constrained()
              ->cascadeOnDelete();

        $table->string('sku'); // артикул (X1-Hybrid-6.0-LV)
        $table->string('name');
        $table->string('currency', 3)->default('USD');

        $table->decimal('supplier_price', 12, 2);

        $table->boolean('is_active')->default(true);

        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
