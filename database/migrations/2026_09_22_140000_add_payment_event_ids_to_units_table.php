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
            // Un evento por semestre: el día que vence cada pago. Se guarda el
            // id que devuelve Graph para poder moverlo o quitarlo después.
            $table->string('first_payment_event_id')->nullable()->after('first_payment_amount');
            $table->string('second_payment_event_id')->nullable()->after('second_payment_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn(['first_payment_event_id', 'second_payment_event_id']);
        });
    }
};
