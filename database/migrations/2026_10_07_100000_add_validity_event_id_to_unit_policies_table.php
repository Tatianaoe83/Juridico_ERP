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
        // El evento de Outlook del fin de vigencia, junto a los de los dos pagos.
        Schema::table('unit_policies', function (Blueprint $table) {
            $table->string('validity_event_id')->nullable()->after('second_payment_event_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('unit_policies', function (Blueprint $table) {
            $table->dropColumn('validity_event_id');
        });
    }
};
