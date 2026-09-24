<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Archivo que respalda a una unidad: un documento oficial, que es de la unidad
 * y no lleva periodo, o el comprobante de un pago, que va ligado a su periodo
 * de póliza. El archivo vive en disco; aquí solo queda su ruta y con qué
 * nombre lo subieron.
 */
class UnitEvidence extends Model
{
    /** Los mismos valores que el enum de la columna `type`. */
    public const OFFICIAL_DOCUMENT = 'official_document';

    public const PAYMENT_RECEIPT = 'payment_receipt';

    /** El plural de «evidence» es igual en inglés: Laravel deduciría `unit_evidence`. */
    protected $table = 'unit_evidences';

    protected $guarded = [];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(UnitPolicy::class, 'unit_policy_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
