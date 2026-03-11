<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('salary_penalties')) {
            return;
        }

        Schema::create('salary_penalties', function (Blueprint $table) {
            $table->id();
            $table->string('staff_group', 50);
            $table->string('staff_name');
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['staff_group', 'staff_name', 'year', 'month'], 'salary_penalties_staff_period_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_penalties');
    }
};
