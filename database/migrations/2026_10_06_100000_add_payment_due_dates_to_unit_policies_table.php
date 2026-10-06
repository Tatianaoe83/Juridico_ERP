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
        // La fecha límite para pagar cada semestre. No es el cierre del
        // semestre (`*_ends_on`): la aseguradora da su propio plazo de pago.
        Schema::table('unit_policies', function (Blueprint $table) {
            $table->date('first_payment_due_on')->nullable()->after('first_payment_ends_on');
            $table->date('second_payment_due_on')->nullable()->after('second_payment_ends_on');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('unit_policies', function (Blueprint $table) {
            $table->dropColumn(['first_payment_due_on', 'second_payment_due_on']);
        });
    }
};
