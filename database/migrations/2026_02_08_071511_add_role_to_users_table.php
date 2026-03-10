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
    Schema::table('users', function ($table) {
        if (!Schema::hasColumn('users', 'role')) {
            $table->string('role', 30)->default('user')->index();
        }
    });
}

public function down(): void
{
    Schema::table('users', function ($table) {
        if (Schema::hasColumn('users', 'role')) {
            $table->dropColumn('role');
        }
    });
}

};
