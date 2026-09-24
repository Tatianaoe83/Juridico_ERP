<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Cada unidad que ya existe se vuelve su primer periodo, con la póliza,
        // las fechas, los importes y los eventos de Outlook que ya tenía.
        $columns = [
            'policy', 'certificate',
            'first_payment_starts_on', 'first_payment_ends_on', 'first_payment_amount', 'first_payment_event_id',
            'second_payment_starts_on', 'second_payment_ends_on', 'second_payment_amount', 'second_payment_event_id',
            'created_by', 'created_at', 'updated_at',
        ];

        DB::table('units')->orderBy('id')->each(function (object $unit) use ($columns) {
            DB::table('unit_policies')->insert([
                'unit_id' => $unit->id,
                ...array_intersect_key((array) $unit, array_flip($columns)),
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('unit_policies')->delete();
    }
};
