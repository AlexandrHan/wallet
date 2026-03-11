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
    Schema::create('fem_container_payments', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('fem_container_id');
        $table->date('paid_at')->nullable();
        $table->decimal('amount', 12, 2)->default(0);
        $table->unsignedBigInteger('created_by')->nullable();
        $table->timestamps();

        $table->foreign('fem_container_id')->references('id')->on('fem_containers')->onDelete('cascade');
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fem_container_payments');
    }
};
