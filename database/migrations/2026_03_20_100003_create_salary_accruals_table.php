<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_accruals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('user_id');
            $table->string('staff_group', 40);  // installation_team / electrician / foreman
            $table->string('staff_name', 100);  // display name
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->string('details', 255)->nullable(); // e.g. "12 кВт × 35"
            $table->string('status', 20)->default('pending'); // pending / paid
            $table->unsignedBigInteger('paid_by')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->unsignedBigInteger('entry_id')->nullable(); // wallet entry reference
            $table->timestamps();

            $table->foreign('project_id')->references('id')->on('sales_projects')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('paid_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_accruals');
    }
};
