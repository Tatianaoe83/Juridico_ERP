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
        // El evento de Outlook del fin de vigencia de la fianza.
        Schema::table('bonds', function (Blueprint $table) {
            $table->string('calendar_event_id')->nullable()->after('valid_until');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bonds', function (Blueprint $table) {
            $table->dropColumn('calendar_event_id');
        });
    }
};
