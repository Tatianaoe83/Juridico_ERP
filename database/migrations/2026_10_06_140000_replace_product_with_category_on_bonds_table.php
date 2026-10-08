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
        // El producto deja de ser texto libre: es la categoría de la fianza
        // (cumplimiento, anticipo, buena calidad, vicios ocultos, suministro
        // o interés fiscal).
        Schema::table('bonds', function (Blueprint $table) {
            $table->dropColumn('product');
        });

        Schema::table('bonds', function (Blueprint $table) {
            $table->enum('category', ['performance', 'advance_payment', 'quality', 'hidden_defects', 'supply', 'tax_interest'])
                ->nullable()
                ->after('source_document');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bonds', function (Blueprint $table) {
            $table->dropColumn('category');
        });

        Schema::table('bonds', function (Blueprint $table) {
            $table->string('product')->nullable()->after('source_document');
        });
    }
};
