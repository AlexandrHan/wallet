<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
Schema::create('reclamations', function (Blueprint $table) {
    $table->id();

    // хто створив (корисно для аудиту)
    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

    // клієнт
    $table->date('reported_at')->nullable();           // дата звернення/створення рекламації
    $table->string('customer_last_name')->nullable();  // прізвище
    $table->string('city')->nullable();                // населений пункт
    $table->string('phone')->nullable();               // телефон

    // підмінний фонд
    $table->boolean('has_loaner')->default(false);
    $table->boolean('need_order_loaner')->default(false);

    // товар / фото
    $table->string('serial_number')->nullable();
    $table->json('photos')->nullable();                // кілька фото

    // етапи
    $table->date('dismantled_at')->nullable();
    $table->date('sent_to_service_at')->nullable();
    $table->string('ttn_to_service')->nullable();
    $table->date('service_received_at')->nullable();
    $table->date('repaired_sent_at')->nullable();
    $table->string('ttn_from_service')->nullable();
    $table->date('installed_at')->nullable();

    // куди повернули підмінний
    $table->string('loaner_return_to')->nullable(); // "warehouse" / "supplier" / текстом поки

    $table->date('completed_at')->nullable();

    // статус + нотатки
    $table->string('status')->default('new'); // new / in_progress / done (потім узгодимо)
    $table->text('note')->nullable();

    $table->timestamps();
});

  }

  public function down(): void
  {
    Schema::dropIfExists('reclamations');
  }
};
