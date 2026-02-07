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
    Schema::create('reclamations', function (Blueprint $table) {
        $table->id();
        $table->string('code')->unique(); // R-00021

        $table->date('reported_at')->nullable();

        $table->string('last_name')->nullable();
        $table->string('city')->nullable();
        $table->string('phone')->nullable();

        $table->boolean('has_loaner')->nullable();
        $table->boolean('loaner_ordered')->default(false);

        $table->string('serial_number')->nullable();

        $table->string('status')->default('open'); // open / wait / done

        $table->unsignedBigInteger('created_by')->nullable();
        $table->timestamps();
    });
}


    /**
     * Reverse the migrations.
     */
public function down(): void
{
    Schema::dropIfExists('reclamations');
}
};
