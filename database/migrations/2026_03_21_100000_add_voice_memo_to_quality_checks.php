<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('quality_checks', function (Blueprint $table) {
            $table->string('voice_memo_path')->nullable()->after('deficiencies');
        });
    }
    public function down(): void {
        Schema::table('quality_checks', function (Blueprint $table) {
            $table->dropColumn('voice_memo_path');
        });
    }
};
