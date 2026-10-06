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
        // Van en el periodo y no en la unidad: cada renglón ya es esa unidad
        // dentro de esa póliza, y al renovar cambian con la póliza nueva.
        Schema::table('unit_policies', function (Blueprint $table) {
            // La aseguradora: el proveedor en la tabla de pólizas y fianzas.
            $table->string('insurer')->nullable()->after('certificate');
            // Si la unidad tiene endoso en esta póliza.
            $table->boolean('endorsement')->default(false)->after('insurer');

            // Solo si se pidió cancelarla y cuándo quedó cancelada.
            $table->date('cancellation_requested_on')->nullable()->after('second_payment_event_id');
            $table->date('cancelled_on')->nullable()->after('cancellation_requested_on');

            $table->text('comments')->nullable()->after('cancelled_on');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('unit_policies', function (Blueprint $table) {
            $table->dropColumn(['insurer', 'endorsement', 'cancellation_requested_on', 'cancelled_on', 'comments']);
        });
    }
};
