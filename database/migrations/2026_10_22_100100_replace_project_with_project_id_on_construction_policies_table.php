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
        // La obra deja de ser texto libre: es una del catálogo, con su
        // dirección. Lo capturado a mano no se pasa: son datos de prueba.
        Schema::table('construction_policies', function (Blueprint $table) {
            $table->dropColumn(['project', 'project_address']);
            // Sin cascada: el catálogo no deja borrar una obra con pólizas.
            $table->foreignId('project_id')->nullable()->after('id')->constrained('projects')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('construction_policies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_id');
            $table->string('project')->nullable()->after('id');
            $table->string('project_address')->nullable()->after('project');
        });
    }
};
