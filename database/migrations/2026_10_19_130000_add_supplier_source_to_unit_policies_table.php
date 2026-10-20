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
        // De dónde sale la póliza: la empresarial la contrata la empresa; la
        // del proveedor es la que lleva beneficiario preferente: la aseguradora
        // la elige quien vende, financia o arrenda la unidad (supplier).
        Schema::table('unit_policies', function (Blueprint $table) {
            $table->enum('source', ['corporate', 'supplier'])->default('corporate')->after('kind');
            $table->string('supplier')->nullable()->after('source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('unit_policies', function (Blueprint $table) {
            $table->dropColumn([
                'source',
                'supplier',
            ]);
        });
    }
};
