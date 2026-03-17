<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_transfers', function (Blueprint $table) {
            $table->string('transfer_type')->default('owner')->after('currency');
            $table->unsignedBigInteger('employee_user_id')->nullable()->after('transfer_type');
            $table->string('comment', 500)->nullable()->after('employee_user_id');
            $table->timestamp('cancelled_at')->nullable()->after('accepted_at');
            $table->unsignedBigInteger('cancelled_by')->nullable()->after('cancelled_at');
        });
    }

    public function down(): void
    {
        Schema::table('cash_transfers', function (Blueprint $table) {
            $table->dropColumn(['transfer_type', 'employee_user_id', 'comment', 'cancelled_at', 'cancelled_by']);
        });
    }
};
