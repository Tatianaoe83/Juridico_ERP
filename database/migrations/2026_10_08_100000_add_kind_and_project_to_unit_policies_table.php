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
        // Además de las de unidades, pólizas de obra: lo mismo (dos semestres,
        // cuotas, cancelación), solo que aseguran una obra y no un vehículo.
        Schema::table('unit_policies', function (Blueprint $table) {
            // Las que ya estaban son todas de unidades.
            $table->enum('kind', ['vehicle', 'construction'])->default('vehicle')->after('id');
            // La de obra no tiene unidad.
            $table->unsignedBigInteger('unit_id')->nullable()->change();
            // Mientras no haya catálogo de obras, la obra se captura a mano.
            $table->string('project')->nullable()->after('unit_id');
            $table->string('project_address')->nullable()->after('project');
            // La de unidad la toma de la unidad; la de obra trae la suya.
            $table->foreignId('business_unit_id')->nullable()->after('project_address')->constrained('business_units')->nullOnDelete();
            // Las coberturas de obra van junto a las de unidades.
            $table->enum('coverage', [
                'civil_liability', 'limited', 'broad', 'broad_plus',
                'civil_works', 'construction_liability', 'erection', 'machinery_equipment',
            ])->nullable()->change();
        });

        // Los comprobantes de una póliza de obra no tienen unidad a la cual ligarse.
        Schema::table('unit_evidences', function (Blueprint $table) {
            $table->unsignedBigInteger('unit_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Sin unidad no caben en la tabla de antes: se van con sus comprobantes.
        DB::table('unit_policies')->where('kind', 'construction')->delete();
        DB::table('unit_evidences')->whereNull('unit_id')->delete();

        Schema::table('unit_evidences', function (Blueprint $table) {
            $table->unsignedBigInteger('unit_id')->nullable(false)->change();
        });

        Schema::table('unit_policies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('business_unit_id');
            $table->dropColumn(['kind', 'project', 'project_address']);
            $table->unsignedBigInteger('unit_id')->nullable(false)->change();
            $table->enum('coverage', ['civil_liability', 'limited', 'broad', 'broad_plus'])->nullable()->change();
        });
    }
};
