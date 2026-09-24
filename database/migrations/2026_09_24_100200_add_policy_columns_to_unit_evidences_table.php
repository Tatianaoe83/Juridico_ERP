<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('unit_evidences', function (Blueprint $table) {
            // A qué periodo pertenece: así el historial muestra qué se subió
            // en cada renovación.
            $table->foreignId('unit_policy_id')->nullable()->after('unit_id')->constrained('unit_policies')->cascadeOnDelete();
            // Documento oficial de la unidad o comprobante de un pago.
            $table->enum('type', ['official_document', 'payment_receipt'])->default('official_document')->after('unit_policy_id');
            // Solo en comprobantes: de cuál de los dos pagos es.
            $table->enum('payment', ['first', 'second'])->nullable()->after('type');
        });

        // Lo que ya estaba subido se liga al único periodo que tiene su unidad.
        DB::table('unit_policies')->orderBy('id')->each(function (object $policy) {
            DB::table('unit_evidences')
                ->where('unit_id', $policy->unit_id)
                ->update(['unit_policy_id' => $policy->id]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('unit_evidences', function (Blueprint $table) {
            $table->dropConstrainedForeignId('unit_policy_id');
            $table->dropColumn(['type', 'payment']);
        });
    }
};
