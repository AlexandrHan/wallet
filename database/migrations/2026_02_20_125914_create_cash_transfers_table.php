<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
public function up(): void
{
    Schema::create('cash_transfers', function (Blueprint $table) {
        $table->id();

        $table->unsignedBigInteger('from_wallet_id');
        $table->unsignedBigInteger('to_wallet_id');

        $table->decimal('amount', 15, 2);
        $table->string('currency', 3);

        $table->string('status')->default('pending');
        // pending | accepted | declined

        $table->unsignedBigInteger('created_by');
        $table->timestamp('accepted_at')->nullable();

        $table->timestamps();
    });
}
};
