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
        // Un renglón por aviso: de qué pago es, cuántos días antes cae y qué
        // evento de Outlook le corresponde, para poder moverlo o quitarlo.
        //
        // No hay interruptor: toda unidad con vencimiento lleva sus avisos.
        Schema::create('unit_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->unsignedTinyInteger('payment');
            // En minutos, como los cuenta Outlook: una semana son 10080 y
            // «en el momento» es 0.
            $table->unsignedInteger('minutes_before');
            $table->string('event_id');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['unit_id', 'payment', 'minutes_before']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unit_reminders');
    }
};
