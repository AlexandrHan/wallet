<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('amo_complectation_projects', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('amo_deal_id')->unique();
            $table->string('client_name');
            $table->string('deal_name')->nullable();
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->unsignedBigInteger('responsible_user_id')->nullable();
            $table->string('responsible_name')->nullable();
            $table->unsignedBigInteger('status_id');
            $table->json('raw_payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amo_complectation_projects');
    }
};

