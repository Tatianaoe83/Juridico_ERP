<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El estado de una licencia sale de su vigencia: guardado solo se calculaba
 * al guardar y se quedaba atrás de la fecha. Ahora se calcula al consultar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('licenses', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn('status');
        });
    }

    public function down(): void
    {
        Schema::table('licenses', function (Blueprint $table) {
            $table->enum('status', ['active', 'expiring', 'expired'])->default('active')->index()->after('valid_time');
        });

        // Se rellena con el estado de hoy, con los mismos cortes que License::statusFor().
        DB::table('licenses')->update([
            'status' => DB::raw(sprintf(
                "case when valid_until < '%s' then 'expired' when valid_until <= '%s' then 'expiring' else 'active' end",
                today()->toDateString(),
                today()->addDays(30)->toDateString(),
            )),
        ]);
    }
};
