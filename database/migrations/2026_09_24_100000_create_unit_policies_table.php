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
        // Cada periodo de póliza de una unidad. La unidad es el vehículo y no
        // cambia; la póliza se renueva cada año con número nuevo, así que cada
        // renovación es un renglón aquí y los anteriores quedan de historial.
        Schema::create('unit_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            // Cambia en cada renovación: no se repite ni entre periodos.
            $table->string('policy')->unique();
            $table->string('certificate')->nullable();

            $table->date('first_payment_starts_on')->nullable();
            $table->date('first_payment_ends_on')->nullable();
            $table->decimal('first_payment_amount', 12, 2)->nullable();
            // Pagado o no: se llena al subir el comprobante. No es un abono.
            $table->date('first_payment_paid_at')->nullable();
            $table->string('first_payment_event_id')->nullable();

            $table->date('second_payment_starts_on')->nullable();
            $table->date('second_payment_ends_on')->nullable();
            $table->decimal('second_payment_amount', 12, 2)->nullable();
            // Al llenarse se crea el periodo siguiente.
            $table->date('second_payment_paid_at')->nullable();
            $table->string('second_payment_event_id')->nullable();

            // De qué periodo salió al renovar; null en el primero de la unidad.
            $table->foreignId('renewed_from_id')->nullable()->constrained('unit_policies')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            // El vigente es el más reciente de la unidad: se busca por aquí.
            $table->index(['unit_id', 'first_payment_starts_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unit_policies');
    }
};
