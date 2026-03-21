<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quality_photos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('quality_check_id');
            $table->string('file_path', 512);
            $table->timestamps();

            $table->foreign('quality_check_id')->references('id')->on('quality_checks')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quality_photos');
    }
};
