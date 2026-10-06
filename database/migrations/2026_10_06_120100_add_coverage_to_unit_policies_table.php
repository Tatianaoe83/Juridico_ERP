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
        // Qué cubre la póliza. Las mismas cuatro para vehículos y maquinaria;
        // vacía en las que ya estaban hasta que alguien la capture.
        Schema::table('unit_policies', function (Blueprint $table) {
            $table->enum('coverage', ['civil_liability', 'limited', 'broad', 'broad_plus'])->nullable()->after('insurer');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('unit_policies', function (Blueprint $table) {
            $table->dropColumn('coverage');
        });
    }
};
