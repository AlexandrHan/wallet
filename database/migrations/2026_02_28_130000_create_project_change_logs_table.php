<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_change_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->string('section_name', 255);
            $table->string('field_name', 255);
            $table->string('action_type', 50)->default('update');
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->string('actor_name', 255)->nullable();
            $table->string('actor_role', 100)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_change_logs');
    }
};
