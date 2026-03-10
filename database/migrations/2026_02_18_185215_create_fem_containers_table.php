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
    Schema::create('fem_containers', function (Blueprint $table) {
        $table->id();
        $table->date('date')->nullable();           // дата контейнера/продажу
        $table->string('name');                     // назва панелей
        $table->decimal('amount', 12, 2)->default(0); // сума контейнера ($)
        $table->unsignedBigInteger('created_by')->nullable();
        $table->timestamps();
    });
}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fem_containers');
    }
};
