<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_requests', function (Blueprint $table) {
            $table->id();
            $table->string('client_name');
            $table->string('settlement');
            $table->string('phone_number')->nullable();
            $table->text('telegram_group_link')->nullable();
            $table->text('geo_location_link')->nullable();
            $table->string('electrician')->nullable();
            $table->string('installation_team')->nullable();
            $table->boolean('is_urgent')->default(false);
            $table->longText('description');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('open');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_requests');
    }
};
