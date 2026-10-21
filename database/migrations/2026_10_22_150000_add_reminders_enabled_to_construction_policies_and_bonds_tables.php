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
        // Si mandan los recordatorios recurrentes por correo, como en licencias
        // y flotillas. Apagado por omisión: se prende desde las acciones de la tabla.
        foreach (['construction_policies', 'bonds'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->boolean('reminders_enabled')->default(false)->after('valid_until');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['construction_policies', 'bonds'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropColumn('reminders_enabled');
            });
        }
    }
};
