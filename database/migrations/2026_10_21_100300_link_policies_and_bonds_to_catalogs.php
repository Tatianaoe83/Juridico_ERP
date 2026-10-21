<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Aseguradora, afianzadora y beneficiario dejan de ser texto libre: cada
     * uno es un registro de su catálogo. [tabla, columna de texto, llave nueva,
     * catálogo, después de qué columna va].
     *
     * Lo capturado como texto no se pasa: todavía son datos de prueba.
     */
    private const COLUMNS = [
        ['unit_policies', 'insurer', 'insurer_id', 'insurers', 'certificate'],
        // El beneficiario preferente de flotillas: la agencia, financiera o arrendadora.
        ['unit_policies', 'supplier', 'supplier_id', 'beneficiaries', 'source'],
        ['construction_policies', 'insurer', 'insurer_id', 'insurers', 'policy'],
        ['bonds', 'beneficiary', 'beneficiary_id', 'beneficiaries', 'bond'],
        ['bonds', 'bonding_company', 'bonding_company_id', 'bonding_companies', 'beneficiary_id'],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (self::COLUMNS as [$table, $text, $key, $catalog, $after]) {
            Schema::table($table, function (Blueprint $blueprint) use ($text, $key, $catalog, $after) {
                $blueprint->dropColumn($text);
                // Sin cascada: el catálogo no deja borrar lo que todavía se usa.
                $blueprint->foreignId($key)->nullable()->after($after)->constrained($catalog)->restrictOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (array_reverse(self::COLUMNS) as [$table, $text, $key]) {
            Schema::table($table, function (Blueprint $blueprint) use ($text, $key) {
                $blueprint->dropConstrainedForeignId($key);
                $blueprint->string($text)->nullable();
            });
        }
    }
};
