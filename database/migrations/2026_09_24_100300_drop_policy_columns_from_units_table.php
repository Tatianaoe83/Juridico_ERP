<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Columnas que ahora viven en unit_policies.
     *
     * @var list<string>
     */
    private const COLUMNS = [
        'certificate',
        'first_payment_starts_on',
        'first_payment_ends_on',
        'first_payment_amount',
        'first_payment_event_id',
        'second_payment_starts_on',
        'second_payment_ends_on',
        'second_payment_amount',
        'second_payment_event_id',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropUnique(['policy']);
            $table->dropColumn(['policy', ...self::COLUMNS]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->string('policy')->nullable()->after('id');
            $table->string('certificate')->nullable()->after('policy');
            $table->date('first_payment_starts_on')->nullable()->after('responsible');
            $table->date('first_payment_ends_on')->nullable()->after('first_payment_starts_on');
            $table->decimal('first_payment_amount', 12, 2)->nullable()->after('first_payment_ends_on');
            $table->string('first_payment_event_id')->nullable()->after('first_payment_amount');
            $table->date('second_payment_starts_on')->nullable()->after('first_payment_event_id');
            $table->date('second_payment_ends_on')->nullable()->after('second_payment_starts_on');
            $table->decimal('second_payment_amount', 12, 2)->nullable()->after('second_payment_ends_on');
            $table->string('second_payment_event_id')->nullable()->after('second_payment_amount');
        });

        // De regreso, cada unidad toma los datos de su periodo más reciente.
        DB::table('units')->orderBy('id')->each(function (object $unit) {
            $policy = DB::table('unit_policies')
                ->where('unit_id', $unit->id)
                ->orderByDesc('first_payment_starts_on')
                ->orderByDesc('id')
                ->first();

            if ($policy) {
                DB::table('units')->where('id', $unit->id)->update(
                    array_intersect_key((array) $policy, array_flip(['policy', ...self::COLUMNS])),
                );
            }
        });

        Schema::table('units', function (Blueprint $table) {
            $table->unique('policy');
        });
    }
};
