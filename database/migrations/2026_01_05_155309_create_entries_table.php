<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();

            $table->date('posting_date');
            $table->string('entry_type'); // income/expense/reversal
            $table->decimal('amount', 18, 2); // завжди додатнє число
            $table->string('title')->nullable();
            $table->text('comment')->nullable();

            // "без дірки": редагування не робимо, тільки сторно
            $table->foreignId('reversal_of_id')->nullable()->constrained('entries')->nullOnDelete();

            // на майбутнє інтеграція
            $table->boolean('synced_to_erp')->default(false);
            $table->string('erp_ref')->nullable();

            $table->string('created_by')->nullable(); // поки рядок, потім прив’яжемо до users
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entries');
    }
};

