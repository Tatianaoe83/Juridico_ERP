<?php

namespace Database\Seeders;

use App\Models\BusinessUnit;
use Illuminate\Database\Seeder;

class BusinessUnitSeeder extends Seeder
{
    /**
     * Las unidades de negocio de la empresa. Es la lista completa: el catálogo
     * no se captura, se siembra, y volver a sembrar no duplica ni renombra.
     */
    private const BUSINESS_UNITS = [
        'Agregados',
        'Constructora',
        'Corporativo',
        'Konkret',
        'Promega',
        'Vías Terrestres',
        'Vidrios Bisel',
    ];

    public function run(): void
    {
        foreach (self::BUSINESS_UNITS as $name) {
            BusinessUnit::firstOrCreate(['name' => $name]);
        }
    }
}
