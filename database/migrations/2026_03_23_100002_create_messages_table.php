<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('from_user_id');
            $table->unsignedBigInteger('to_user_id');
            $table->text('message');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('read_at')->nullable();

            $table->index(['to_user_id', 'read_at']);
            $table->index(['from_user_id', 'to_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
