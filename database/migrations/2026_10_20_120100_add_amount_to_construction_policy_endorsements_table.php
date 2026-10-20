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
        // La prima adicional que cobra la aseguradora por extender, con IVA
        // incluido. Se suma al monto de la póliza.
        Schema::table('construction_policy_endorsements', function (Blueprint $table) {
            $table->decimal('amount', 14, 2)->nullable()->after('valid_until');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('construction_policy_endorsements', function (Blueprint $table) {
            $table->dropColumn('amount');
        });
    }
};
