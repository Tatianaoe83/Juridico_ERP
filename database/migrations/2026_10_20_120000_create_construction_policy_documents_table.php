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
        // Documentos de la póliza de obra (la póliza misma, anexos…), aparte de
        // los de sus endosos. El archivo vive en disco; aquí su ruta y con qué
        // nombre lo subieron.
        Schema::create('construction_policy_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('construction_policy_id')->constrained('construction_policies')->cascadeOnDelete();
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
        Schema::dropIfExists('construction_policy_documents');
    }
};
