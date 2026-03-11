<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
public function up(): void
{
    Schema::table('bank_accounts', function (Blueprint $table) {
        $table->string('api_account_id')->nullable()->after('bank_code');
        $table->string('iban')->nullable()->after('api_account_id');
        $table->decimal('balance', 15, 2)->default(0)->after('currency');
        $table->dateTime('balance_at')->nullable()->after('balance');
    });
}

public function down(): void
{
    Schema::table('bank_accounts', function (Blueprint $table) {
        $table->dropColumn([
            'api_account_id',
            'iban',
            'balance',
            'balance_at',
        ]);
    });
}

};
