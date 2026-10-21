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
        // De la licencia solo importa el día que vence: el evento es de todo
        // el día y su recordatorio de Outlook es fijo, el mismo día. Los avisos
        // con anticipación irán por correo, así que el aviso por licencia se va.
        Schema::dropIfExists('reminder_licenses');

        Schema::table('licenses', function (Blueprint $table) {
            $table->dropColumn('valid_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('licenses', function (Blueprint $table) {
            $table->time('valid_time')->nullable()->after('valid_until');
        });

        Schema::create('reminder_licenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_id')->unique()->constrained('licenses')->cascadeOnDelete();
            $table->unsignedInteger('minutes_before');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });
    }
};
