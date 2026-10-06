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
        // Qué es la unidad: un vehículo de calle o maquinaria. Lo que ya está
        // capturado son vehículos.
        Schema::table('units', function (Blueprint $table) {
            $table->enum('type', ['vehicle', 'machinery'])->default('vehicle')->after('business_unit_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
