<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Archivo que respalda a una unidad. El archivo vive en disco; aquí solo
 * queda su ruta y con qué nombre lo subieron.
 */
class UnitEvidence extends Model
{
    /** El plural de «evidence» es igual en inglés: Laravel deduciría `unit_evidence`. */
    protected $table = 'unit_evidences';

    protected $guarded = [];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
