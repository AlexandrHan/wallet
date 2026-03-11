<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_rules', function (Blueprint $table) {
            $table->id();
            $table->string('staff_group');
            $table->string('staff_name');
            $table->string('mode');
            $table->string('currency', 3)->default('UAH');
            $table->decimal('fixed_amount', 12, 2)->nullable();
            $table->decimal('piecework_grid_le_50', 12, 2)->nullable();
            $table->decimal('piecework_grid_gt_50', 12, 2)->nullable();
            $table->decimal('piecework_hybrid_le_50', 12, 2)->nullable();
            $table->decimal('piecework_hybrid_gt_50', 12, 2)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['staff_group', 'staff_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_rules');
    }
};
