<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // La empresa deja de ser texto libre: es una unidad de negocio del catálogo.
        Schema::table('licenses', function (Blueprint $table) {
            $table->foreignId('business_unit_id')->nullable()->after('name')->constrained('business_units')->nullOnDelete();
        });

        // Lo capturado a mano se liga por nombre, sin importar mayúsculas
        // («CORPORATIVO» → Corporativo). Lo que no está en el catálogo queda
        // sin empresa.
        $units = DB::table('business_units')->pluck('id', 'name')
            ->mapWithKeys(fn (int $id, string $name) => [mb_strtolower($name) => $id]);

        DB::table('licenses')->whereNotNull('company')->orderBy('id')->each(function (object $license) use ($units) {
            DB::table('licenses')
                ->where('id', $license->id)
                ->update(['business_unit_id' => $units[mb_strtolower(trim($license->company))] ?? null]);
        });

        Schema::table('licenses', function (Blueprint $table) {
            $table->dropColumn('company');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('licenses', function (Blueprint $table) {
            $table->string('company')->nullable()->after('name');
        });

        // De regreso, cada licencia toma el nombre de su unidad de negocio.
        DB::table('licenses')->whereNotNull('business_unit_id')->orderBy('id')->each(function (object $license) {
            DB::table('licenses')
                ->where('id', $license->id)
                ->update(['company' => DB::table('business_units')->where('id', $license->business_unit_id)->value('name')]);
        });

        Schema::table('licenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('business_unit_id');
        });
    }
};
