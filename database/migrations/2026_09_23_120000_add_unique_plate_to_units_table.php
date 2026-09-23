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
        Schema::table('units', function (Blueprint $table) {
            // Una placa pertenece a un solo vehículo. Sigue siendo opcional:
            // varias unidades sin placa conviven porque los NULL no chocan.
            $table->dropIndex(['plate']);
            $table->unique('plate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropUnique(['plate']);
            $table->index('plate');
        });
    }
};
