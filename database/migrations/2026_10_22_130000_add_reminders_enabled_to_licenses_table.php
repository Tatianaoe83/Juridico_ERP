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
        // Si la licencia manda los recordatorios recurrentes por correo.
        // Apagado por omisión: se prende desde las acciones de la tabla.
        Schema::table('licenses', function (Blueprint $table) {
            $table->boolean('reminders_enabled')->default(false)->after('valid_until');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('licenses', function (Blueprint $table) {
            $table->dropColumn('reminders_enabled');
        });
    }
};
