<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('microsoft_accounts', function (Blueprint $table) {
            // Los ids de calendario de Graph son por buzón, así que no pueden
            // vivir en una sola variable de entorno compartida por todos.
            $table->string('calendar_id', 512)->nullable()->after('display_name');
        });

        // Adopta el calendario que ya estaba en el .env para no crear otro.
        if (filled($legacy = env('MS_CALENDAR_ID'))) {
            DB::table('microsoft_accounts')
                ->whereNull('calendar_id')
                ->update(['calendar_id' => $legacy]);
        }
    }

    public function down(): void
    {
        Schema::table('microsoft_accounts', function (Blueprint $table) {
            $table->dropColumn('calendar_id');
        });
    }
};
