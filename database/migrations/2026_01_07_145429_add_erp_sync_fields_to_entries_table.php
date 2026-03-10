<?php

/**use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

     
    public function up(): void
    {
        Schema::table('entries', function (Blueprint $table) {
            //
        });
    }


     
    public function down(): void
    {
        Schema::table('entries', function (Blueprint $table) {
            //
        });
    }
}*/


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('entries', function (Blueprint $table) {
            $table->string('erp_journal_entry_name')->nullable()->index();
            $table->timestamp('erp_submitted_at')->nullable();
            $table->text('erp_error')->nullable();
        });
    }

    public function down(): void {
        Schema::table('entries', function (Blueprint $table) {
            $table->dropColumn(['erp_journal_entry_name','erp_submitted_at','erp_error']);
        });
    }
};
