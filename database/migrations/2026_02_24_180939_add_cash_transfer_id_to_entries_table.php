<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
public function up()
{
    Schema::table('entries', function (Blueprint $table) {
        $table->unsignedBigInteger('cash_transfer_id')->nullable()->after('id');
    });
}

public function down()
{
    Schema::table('entries', function (Blueprint $table) {
        $table->dropColumn('cash_transfer_id');
    });
}
};
