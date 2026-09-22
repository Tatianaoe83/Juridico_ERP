<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Unidad de la flotilla: un vehículo con su póliza y sus dos pagos semestrales.
 */
class Unit extends Model
{
    /** Los mismos valores que el enum de la columna `status`. */
    public const STATUSES = ['active', 'maintenance', 'inactive'];

    /** IVA sobre el costo anual. */
    public const TAX_RATE = 0.16;

    /** Días antes del pago en que la unidad entra en «por vencer». */
    public const WARNING_DAYS = 30;

    protected $guarded = [];

    /** Costo anual: la suma de los dos pagos semestrales. */
    public function annualCost(): float
    {
        return (float) $this->first_payment_amount + (float) $this->second_payment_amount;
    }

    /** IVA del costo anual. No se guarda: se calcula para no salir de cuadre. */
    public function tax(): float
    {
        return round($this->annualCost() * self::TAX_RATE, 2);
    }

    /** Costo anual con IVA. */
    public function total(): float
    {
        return round($this->annualCost() + $this->tax(), 2);
    }

    /**
     * El pago que sigue: el inicio del semestre más cercano que aún no pasa.
     * Si los dos ya pasaron no hay nada por vencer y devuelve null.
     */
    public function nextPaymentDate(): ?Carbon
    {
        $today = today();

        return collect([$this->first_payment_starts_on, $this->second_payment_starts_on])
            ->filter()
            ->filter(fn (Carbon $date) => $date->gte($today))
            ->sort()
            ->first();
    }

    /** Si el siguiente pago cae dentro de la ventana de aviso. */
    public function paymentDueSoon(): bool
    {
        $next = $this->nextPaymentDate();

        return $next !== null && $next->lte(today()->addDays(self::WARNING_DAYS));
    }

    protected function casts(): array
    {
        return [
            'first_payment_starts_on' => 'date',
            'first_payment_ends_on' => 'date',
            'second_payment_starts_on' => 'date',
            'second_payment_ends_on' => 'date',
            'first_payment_amount' => 'decimal:2',
            'second_payment_amount' => 'decimal:2',
            'usa_canada_endorsement' => 'boolean',
        ];
    }

    /** A qué unidad de negocio pertenece; null si aún no se le asigna. */
    public function businessUnit(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class);
    }

    /** Quién dio de alta el registro. */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Archivos que respaldan a la unidad: facturas, pólizas, fotos. */
    public function evidences(): HasMany
    {
        return $this->hasMany(UnitEvidence::class);
    }
}
