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
        // Las facturas de cada cuota (PDF y XML), aparte del comprobante de pago.
        Schema::table('unit_evidences', function (Blueprint $table) {
            $table->enum('type', ['official_document', 'payment_receipt', 'invoice'])->default('official_document')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('unit_evidences')->where('type', 'invoice')->delete();

        Schema::table('unit_evidences', function (Blueprint $table) {
            $table->enum('type', ['official_document', 'payment_receipt'])->default('official_document')->change();
        });
    }
};
