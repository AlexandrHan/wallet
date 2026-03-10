<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('reclamation_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('reclamation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('step_key')->nullable();          // напр: reported, installed...
            $table->string('action', 40);                    // напр: step_update, file_upload
            $table->json('payload')->nullable();             // що саме змінили (нові дані / файли)

            $table->timestamps();

            $table->index(['reclamation_id', 'created_at']);
            $table->index(['step_key', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reclamation_logs');
    }
};
