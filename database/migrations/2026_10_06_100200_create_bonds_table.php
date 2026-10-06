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
        // Fianzas: la garantía que una afianzadora da a un beneficiario sobre
        // un contrato o asunto. No van ligadas a unidades.
        Schema::create('bonds', function (Blueprint $table) {
            $table->id();
            // El número de la fianza; no se repite.
            $table->string('bond')->unique();
            $table->string('beneficiary')->nullable();
            // La afianzadora: el proveedor en la tabla de pólizas y fianzas.
            $table->string('bonding_company')->nullable();
            $table->decimal('amount', 14, 2)->nullable();

            $table->date('requested_on')->nullable();
            $table->date('issued_on')->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();

            // Documento que la origina (contrato, pedido, licitación…).
            $table->string('source_document')->nullable();
            // Tipo de fianza: cumplimiento, anticipo, vicios ocultos…
            $table->string('product')->nullable();
            // El asunto o contrato al que se refiere.
            $table->string('related')->nullable();

            // Solo si se pidió cancelarla y cuándo quedó cancelada.
            $table->date('cancellation_requested_on')->nullable();
            $table->date('cancelled_on')->nullable();

            $table->text('comments')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index('valid_until');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bonds');
    }
};
