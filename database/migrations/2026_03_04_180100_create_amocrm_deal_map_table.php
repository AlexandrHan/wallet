<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('amocrm_deal_map', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('amo_deal_id')->unique();
            $table->unsignedBigInteger('wallet_project_id');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('wallet_project_id')
                ->references('id')
                ->on('sales_projects')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amocrm_deal_map');
    }
};
