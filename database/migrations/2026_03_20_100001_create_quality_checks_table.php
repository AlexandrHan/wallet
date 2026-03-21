<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quality_checks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('created_by');   // worker who submitted
            $table->unsignedBigInteger('approved_by')->nullable(); // foreman/owner
            $table->string('status', 20)->default('pending'); // pending / approved
            $table->text('deficiencies')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->foreign('project_id')->references('id')->on('sales_projects')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quality_checks');
    }
};
