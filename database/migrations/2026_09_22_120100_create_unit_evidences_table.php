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
        Schema::create('unit_evidences', function (Blueprint $table) {
            $table->id();
            // Al borrar la unidad se van sus evidencias: sin ella no significan nada.
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            // Ruta en el disco; el nombre original se guarda aparte para
            // poder descargar el archivo como lo subieron.
            $table->string('path');
            $table->string('name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unit_evidences');
    }
};
