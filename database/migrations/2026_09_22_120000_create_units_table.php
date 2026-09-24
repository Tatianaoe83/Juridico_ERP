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
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            // La póliza identifica a la unidad para quien la opera: es única y
            // se busca por ella, pero el id sigue siendo la llave del sistema.
            $table->string('policy')->unique();
            $table->string('certificate')->nullable();
            // A qué unidad de negocio pertenece. Si se borra del catálogo, la
            // unidad se queda sin ella en vez de irse con la baja.
            $table->foreignId('business_unit_id')->nullable()->constrained('business_units')->nullOnDelete();
            $table->string('brand')->index();
            $table->string('model');
            $table->string('serial_number')->nullable()->unique();
            $table->string('plate')->nullable()->index();
            $table->string('economic_number')->nullable();
            $table->string('responsible')->nullable();

            // Dos pagos semestrales. El rango de meses se captura completo
            // porque no siempre es enero-junio: puede ir de diciembre 2026 a
            // mayo 2027 y cambiar de una unidad a otra.
            $table->date('first_payment_starts_on')->nullable();
            $table->date('first_payment_ends_on')->nullable();
            $table->decimal('first_payment_amount', 12, 2)->nullable();
            $table->date('second_payment_starts_on')->nullable();
            $table->date('second_payment_ends_on')->nullable();
            $table->decimal('second_payment_amount', 12, 2)->nullable();

            // El costo anual y el IVA no se guardan: son la suma de los dos
            // pagos y su 16%. Guardarlos abriría la puerta a que no cuadren.
            $table->enum('status', ['active', 'maintenance', 'inactive'])->default('active')->index();
            $table->text('comments')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
