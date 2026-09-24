<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Un periodo de póliza de una unidad: su número y sus dos pagos semestrales.
 *
 * Se renueva cada año con número nuevo. Al pagar el segundo semestre se crea
 * el siguiente periodo y este queda en el historial.
 */
class UnitPolicy extends Model
{
    /** Los dos pagos, tal como van en el prefijo de sus columnas. */
    public const PAYMENTS = ['first', 'second'];

    /** IVA que traen incluido los importes capturados. */
    public const TAX_RATE = 0.16;

    /** Duración de cada semestre, en meses. */
    public const SEMESTER_MONTHS = 6;

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

    /** Si ese pago ('first' | 'second') ya tiene comprobante. */
    public function isPaid(string $payment): bool
    {
        return $this->{"{$payment}_payment_paid_at"} !== null;
    }

    /** Si de este periodo ya salió el siguiente. */
    public function isRenewed(): bool
    {
        return $this->renewal()->exists();
    }

    /**
     * El pago que sigue: el cierre más cercano de los semestres sin pagar que
     * aún no pasa. Vence cuando el periodo termina, no cuando empieza.
     */
    public function nextPaymentDate(): ?Carbon
    {
        $today = today();

        return collect(self::PAYMENTS)
            ->reject(fn (string $payment) => $this->isPaid($payment))
            ->map(fn (string $payment) => $this->{"{$payment}_payment_ends_on"})
            ->filter()
            ->filter(fn (Carbon $date) => $date->gte($today))
            ->sort()
            ->first();
    }

    /**
     * El semestre más reciente: el segundo en cuanto arranca, antes el primero.
     * Es el que se muestra en la tabla de flotillas.
     */
    public function currentSemester(): string
    {
        $start = $this->second_payment_starts_on;

        return $start && $start->lte(today()) ? 'second' : 'first';
    }

    /**
     * Cómo va un semestre: 'paid' con comprobante, 'overdue' si su fecha
     * límite ya pasó sin pagar y 'pending' si aún está a tiempo.
     */
    public function paymentStatus(string $payment): string
    {
        if ($this->isPaid($payment)) {
            return 'paid';
        }

        $deadline = $this->{"{$payment}_payment_ends_on"};

        return $deadline && $deadline->lt(today()) ? 'overdue' : 'pending';
    }

    /**
     * Cómo va el año: el semestre en curso manda (en el primero, si ya se
     * pagó está al día; en el segundo, si se pagó ese), salvo que haya
     * quedado un semestre vencido sin pagar, que lo deja vencido.
     */
    public function annualStatus(): string
    {
        $overdue = collect(self::PAYMENTS)->contains(fn (string $payment) => $this->paymentStatus($payment) === 'overdue');

        return $overdue ? 'overdue' : $this->paymentStatus($this->currentSemester());
    }

    protected function casts(): array
    {
        return [
            'first_payment_starts_on' => 'date',
            'first_payment_ends_on' => 'date',
            'first_payment_paid_at' => 'date',
            'second_payment_starts_on' => 'date',
            'second_payment_ends_on' => 'date',
            'second_payment_paid_at' => 'date',
            'first_payment_amount' => 'decimal:2',
            'second_payment_amount' => 'decimal:2',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /** El periodo del que salió al renovar. */
    public function renewedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'renewed_from_id');
    }

    /** El periodo que salió de este al pagar el segundo semestre. */
    public function renewal(): HasOne
    {
        return $this->hasOne(self::class, 'renewed_from_id');
    }

    /** Documentos y comprobantes subidos en este periodo. */
    public function evidences(): HasMany
    {
        return $this->hasMany(UnitEvidence::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
