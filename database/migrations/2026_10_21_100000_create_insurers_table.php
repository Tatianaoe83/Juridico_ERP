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
        // Aseguradoras: quienes emiten las pólizas de seguro (obra, flotilla y maquinaria).
        Schema::create('insurers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            // La que ganó la subasta: sale por defecto en los automóviles
            // empresariales. Solo una a la vez; lo cuida CatalogController.
            $table->boolean('preferred')->default(false);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('insurers');
    }
};
