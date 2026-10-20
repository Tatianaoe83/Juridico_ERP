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
        // Si la unidad está financiada en esta póliza. Va junto al endoso: se
        // captura igual, con un interruptor, y cambia con cada renovación.
        Schema::table('unit_policies', function (Blueprint $table) {
            $table->boolean('financing')->default(false)->after('endorsement');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('unit_policies', function (Blueprint $table) {
            $table->dropColumn('financing');
        });
    }
};
