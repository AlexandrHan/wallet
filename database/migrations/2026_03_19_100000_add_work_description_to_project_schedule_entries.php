<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_schedule_entries', function (Blueprint $table) {
            $table->text('work_description')->nullable()->after('source');
        });
    }

    public function down(): void
    {
        Schema::table('project_schedule_entries', function (Blueprint $table) {
            $table->dropColumn('work_description');
        });
    }
};
