<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_schedule_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('sales_projects')->cascadeOnDelete();
            $table->string('assignment_field', 64);
            $table->string('assignment_value', 255);
            $table->date('work_date');
            $table->string('source', 64)->default('automation');
            $table->timestamps();

            $table->unique(
                ['project_id', 'assignment_field', 'assignment_value', 'work_date'],
                'project_schedule_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_schedule_entries');
    }
};
