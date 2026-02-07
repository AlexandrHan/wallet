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
    Schema::create('reclamation_steps', function (Blueprint $table) {
        $table->id();

        $table->foreignId('reclamation_id')
            ->constrained('reclamations')
            ->cascadeOnDelete();

        $table->string('step_key');       // dismantled, shipped_to_service, installed, closed...
        $table->date('done_date')->nullable();

        $table->text('note')->nullable(); // коментар/опис
        $table->string('ttn')->nullable();// ТТН
        $table->json('files')->nullable();// фото/файли

        $table->timestamps();

        $table->unique(['reclamation_id', 'step_key']);
    });
}


    /**
     * Reverse the migrations.
     */
public function down(): void
{
    Schema::dropIfExists('reclamation_steps');
}

};
