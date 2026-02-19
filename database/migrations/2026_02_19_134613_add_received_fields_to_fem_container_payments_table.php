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
        Schema::table('fem_container_payments', function (Blueprint $table) {
            $table->unsignedTinyInteger('is_received')->default(0)->after('amount');
            $table->unsignedBigInteger('received_by')->nullable()->after('created_by');
            $table->timestamp('received_at')->nullable()->after('received_by');

            $table->foreign('received_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['fem_container_id', 'is_received']);
        });
    }

    public function down(): void
    {
        Schema::table('fem_container_payments', function (Blueprint $table) {
            $table->dropForeign(['received_by']);
            $table->dropIndex(['fem_container_id', 'is_received']);

            $table->dropColumn(['is_received','received_by','received_at']);
        });
    }

};
