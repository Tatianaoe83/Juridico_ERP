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
        // Si la póliza manda los recordatorios recurrentes por correo, como en
        // licencias. Apagado por omisión: se prende desde las acciones de la tabla.
        Schema::table('unit_policies', function (Blueprint $table) {
            $table->boolean('reminders_enabled')->default(false)->after('second_payment_ends_on');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('unit_policies', function (Blueprint $table) {
            $table->dropColumn('reminders_enabled');
        });
    }
};
