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
        // Pólizas de obra, aparte de las de Flotillas: aseguran una obra, no
        // una unidad. Sin semestres ni cuotas: un monto y su vigencia, que por
        // lo general corre hasta la terminación de la obra.
        Schema::create('construction_policies', function (Blueprint $table) {
            $table->id();
            // Mientras no haya catálogo de obras, la obra se captura a mano.
            $table->string('project');
            $table->string('project_address')->nullable();
            $table->foreignId('business_unit_id')->nullable()->constrained('business_units')->nullOnDelete();
            // El número de la póliza; no se repite. Un endoso lo conserva.
            $table->string('policy')->unique();
            $table->string('insurer')->nullable();
            $table->enum('coverage', ['civil_works', 'construction_liability', 'erection', 'machinery_equipment']);

            $table->date('valid_from');
            // La terminación de la obra o un año exacto; un endoso la recorre.
            $table->date('valid_until');
            // Lo que se paga, con IVA incluido. No se calcula de la obra: el
            // porcentaje y la base varían, se captura el monto que resulta.
            $table->decimal('amount', 14, 2)->nullable();

            $table->text('comments')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index('valid_until');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('construction_policies');
    }
};
