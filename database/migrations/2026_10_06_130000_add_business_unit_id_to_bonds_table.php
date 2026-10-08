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
        // La fianza no va ligada a una unidad: su unidad de negocio se captura
        // aparte. Las pólizas la toman de su unidad.
        Schema::table('bonds', function (Blueprint $table) {
            $table->foreignId('business_unit_id')->nullable()->after('id')->constrained('business_units')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bonds', function (Blueprint $table) {
            $table->dropConstrainedForeignId('business_unit_id');
        });
    }
};
