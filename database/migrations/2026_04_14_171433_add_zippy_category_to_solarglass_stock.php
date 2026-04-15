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
        Schema::table('solarglass_stock', function (Blueprint $table) {
            $table->string('zippy_category')->nullable()->after('qty');
            $table->string('zippy_cat_name')->nullable()->after('zippy_category');
            $table->unsignedSmallInteger('zippy_cat_id')->nullable()->after('zippy_cat_name');
        });
    }

    public function down(): void
    {
        Schema::table('solarglass_stock', function (Blueprint $table) {
            $table->dropColumn(['zippy_category', 'zippy_cat_name', 'zippy_cat_id']);
        });
    }
};
