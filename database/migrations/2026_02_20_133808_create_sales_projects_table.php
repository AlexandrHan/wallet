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
        Schema::create('sales_projects', function (Blueprint $table) {
            $table->id();

            $table->string('client_name'); // Прізвище Ім'я
            $table->decimal('total_amount', 15, 2);
            $table->decimal('advance_amount', 15, 2)->default(0);
            $table->decimal('remaining_amount', 15, 2);

            $table->string('currency', 3); // UAH USD EUR

            $table->unsignedBigInteger('created_by');

            $table->string('status')->default('active');
            // active | completed | cancelled

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_projects');
    }
};
