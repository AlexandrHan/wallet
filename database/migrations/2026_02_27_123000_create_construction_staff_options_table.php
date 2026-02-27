<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('construction_staff_options', function (Blueprint $table) {
            $table->id();
            $table->string('type', 32); // electrician | installation_team
            $table->string('name', 255);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['type', 'name']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('construction_staff_options');
    }
};
