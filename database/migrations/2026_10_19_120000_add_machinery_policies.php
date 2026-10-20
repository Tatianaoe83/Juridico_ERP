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
        // Pólizas de maquinaria: una sola póliza cubre varias máquinas. Usa las
        // mismas columnas de vigencia que las demás (inicio en
        // first_payment_starts_on, fin en second_payment_ends_on) y un solo
        // pago (first_payment_amount y first_payment_due_on).
        Schema::table('unit_policies', function (Blueprint $table) {
            $table->enum('kind', ['vehicle', 'construction', 'machinery'])->default('vehicle')->change();
            // EC (equipo de contratistas) es nueva; RC reusa civil_liability.
            $table->enum('coverage', [
                'civil_liability', 'limited', 'broad', 'broad_plus',
                'civil_works', 'construction_liability', 'erection', 'machinery_equipment',
                'contractors_equipment',
            ])->nullable()->change();
            // Solo en maquinaria: EC se paga en dólares y RC en pesos.
            $table->enum('currency', ['MXN', 'USD'])->nullable()->after('coverage');
        });

        Schema::table('units', function (Blueprint $table) {
            // La máquina pertenece a la póliza de maquinaria que la cubre. Los
            // vehículos no lo usan: su póliza apunta a ellos con unit_id.
            $table->foreignId('unit_policy_id')->nullable()->after('business_unit_id')->constrained('unit_policies')->nullOnDelete();
            // Texto libre que identifica la máquina, como un número de serie.
            $table->string('machine_type')->nullable()->after('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Las máquinas y sus pólizas no caben en el esquema de antes.
        $policies = DB::table('unit_policies')->where('kind', 'machinery')->pluck('id');
        DB::table('units')->whereIn('unit_policy_id', $policies)->delete();
        DB::table('unit_policies')->whereIn('id', $policies)->delete();
        DB::table('unit_policies')->where('coverage', 'contractors_equipment')->update(['coverage' => null]);

        Schema::table('units', function (Blueprint $table) {
            $table->dropConstrainedForeignId('unit_policy_id');
            $table->dropColumn('machine_type');
        });

        Schema::table('unit_policies', function (Blueprint $table) {
            $table->dropColumn('currency');
            $table->enum('coverage', [
                'civil_liability', 'limited', 'broad', 'broad_plus',
                'civil_works', 'construction_liability', 'erection', 'machinery_equipment',
            ])->nullable()->change();
            $table->enum('kind', ['vehicle', 'construction'])->default('vehicle')->change();
        });
    }
};
