<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('reclamation_photos', function (Blueprint $table) {
      $table->id();
      $table->foreignId('reclamation_id')->constrained()->cascadeOnDelete();

      $table->string('path');          // storage path
      $table->string('caption')->nullable();

      $table->timestamps();
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('reclamation_photos');
  }
};
