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

    /** IVA que traen incluido los importes capturados. */
    public const TAX_RATE = 0.16;

    /** Días antes del pago en que la unidad entra en «por vencer». */
    public const WARNING_DAYS = 30;

    protected $guarded = [];

    /** Costo anual: la suma de los dos pagos semestrales, con IVA incluido. */
    public function annualCost(): float
    {
        return (float) $this->first_payment_amount + (float) $this->second_payment_amount;
    }

    /**
     * El IVA que ya viene dentro del costo anual.
     *
     * Los importes se capturan como se pagan, o sea con impuesto incluido, así
     * que aquí se desglosa: no se le suma nada a lo que ya se pagó.
     */
    public function tax(): float
    {
        return round($this->annualCost() * self::TAX_RATE / (1 + self::TAX_RATE), 2);
    }

    /** Costo anual sin IVA. */
    public function subtotal(): float
    {
        return round($this->annualCost() - $this->tax(), 2);
    }

    /**
     * El pago que sigue: el cierre del semestre más cercano que aún no pasa.
     *
     * Vence cuando el periodo termina, no cuando empieza: un semestre que va
     * de abril a octubre se paga en octubre.
     *
     * Si los dos ya pasaron no hay nada por vencer y devuelve null.
     */
    public function nextPaymentDate(): ?Carbon
    {
        $today = today();

        return collect([$this->first_payment_ends_on, $this->second_payment_ends_on])
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
