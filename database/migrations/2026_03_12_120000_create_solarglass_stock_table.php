<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solarglass_stock', function (Blueprint $table) {
            $table->id();
            $table->string('item_code')->index();
            $table->string('item_name')->nullable();
            $table->decimal('qty', 14, 4)->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['item_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solarglass_stock');
    }
};
