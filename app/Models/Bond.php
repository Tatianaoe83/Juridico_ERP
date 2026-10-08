<?php

namespace App\Models;

use App\Support\CoverageStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una fianza: la afianzadora garantiza ante un beneficiario el cumplimiento
 * de un contrato o asunto, por un monto y durante una vigencia.
 */
class Bond extends Model
{
    /**
     * Los mismos valores que el enum de la columna `category`: cumplimiento,
     * anticipo, buena calidad, vicios ocultos, suministro e interés fiscal.
     */
    public const CATEGORIES = ['performance', 'advance_payment', 'quality', 'hidden_defects', 'supply', 'tax_interest'];

    protected $guarded = [];

    /** Vigente, por vencer, vencida, en cancelación o cancelada. */
    public function status(): string
    {
        return CoverageStatus::of($this->valid_until, $this->cancelled_on, $this->cancellation_requested_on);
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'requested_on' => 'date',
            'issued_on' => 'date',
            'valid_from' => 'date',
            'valid_until' => 'date',
            'cancellation_requested_on' => 'date',
            'cancelled_on' => 'date',
        ];
    }

    public function businessUnit(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
