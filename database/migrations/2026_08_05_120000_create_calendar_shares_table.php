<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Espejo local de con quién comparte su calendario cada cuenta vinculada.
 *
 * La verdad sigue siendo Outlook —`calendarPermissions` de Graph—, pero sin
 * copia local la app no puede responder «qué calendarios puede ver esta
 * persona» sin recorrer el buzón de todos los dueños en cada visita.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('microsoft_account_id')->constrained()->cascadeOnDelete();
            // Se guarda el correo y no un user_id: se comparte con quien sea,
            // exista o no todavía como cuenta de la app.
            $table->string('email');
            $table->string('role')->default('read');
            // Id del permiso en Graph, para poder revocarlo allá.
            $table->string('permission_id')->nullable();
            $table->timestamps();

            $table->unique(['microsoft_account_id', 'email']);
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_shares');
    }
};
