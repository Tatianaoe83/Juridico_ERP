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
        // Endosos de extensión: la misma póliza, con el mismo número, solo
        // recorre su fin de vigencia. Cada uno queda de historial.
        Schema::create('construction_policy_endorsements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('construction_policy_id')->constrained('construction_policies')->cascadeOnDelete();
            // El número del endoso, si la aseguradora le pone uno.
            $table->string('endorsement')->nullable();
            // El fin que tenía la póliza antes del endoso y el nuevo. Con el
            // anterior, quitar el último endoso deja la vigencia como estaba.
            $table->date('previous_valid_until');
            $table->date('valid_until');

            // El documento del endoso: vive en disco, aquí su ruta y con qué
            // nombre lo subieron.
            $table->string('path')->nullable();
            $table->string('name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('construction_policy_endorsements');
    }
};
