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
        // Una fianza puede cubrir varias cosas a la vez (cumplimiento y
        // anticipo, por ejemplo): la cobertura pasa de un valor a una lista.
        // Lo capturado no se pasa: son datos de prueba.
        Schema::table('bonds', function (Blueprint $table) {
            $table->dropColumn('category');
            $table->json('categories')->nullable()->after('source_document');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bonds', function (Blueprint $table) {
            $table->dropColumn('categories');
            $table->enum('category', ['performance', 'advance_payment', 'quality', 'hidden_defects', 'supply', 'tax_interest'])
                ->nullable()
                ->after('source_document');
        });
    }
};
