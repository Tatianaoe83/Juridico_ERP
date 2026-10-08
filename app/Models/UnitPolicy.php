<?php

namespace App\Models;

use App\Support\CoverageStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Un periodo de póliza: su número y sus dos pagos semestrales. Asegura una
 * unidad (vehicular) o una obra; todo lo demás es igual en las dos.
 *
 * Se renueva cada año con número nuevo. Al pagar el segundo semestre se crea
 * el siguiente periodo y este queda en el historial.
 */
class UnitPolicy extends Model
{
    /** Los dos pagos, tal como van en el prefijo de sus columnas. */
    public const PAYMENTS = ['first', 'second'];

    /** Los mismos valores que el enum de la columna `kind`. */
    public const VEHICLE = 'vehicle';

    public const CONSTRUCTION = 'construction';

    public const KINDS = [self::VEHICLE, self::CONSTRUCTION];

    /**
     * Las coberturas de cada tipo, las mismas que el enum de la columna
     * `coverage`. Vehicular: responsabilidad civil, limitada, amplia y amplia
     * plus, igual para vehículos y maquinaria. Obra: obra civil, responsabilidad
     * civil de construcción, montaje y maquinaria y equipo.
     */
    public const COVERAGES = [
        self::VEHICLE => ['civil_liability', 'limited', 'broad', 'broad_plus'],
        self::CONSTRUCTION => ['civil_works', 'construction_liability', 'erection', 'machinery_equipment'],
    ];

    /** IVA que traen incluido los importes capturados. */
    public const TAX_RATE = 0.16;

    /** Duración de cada semestre, en meses. */
    public const SEMESTER_MONTHS = 6;

    protected $guarded = [];

    public function isConstruction(): bool
    {
        return $this->kind === self::CONSTRUCTION;
    }

    /**
     * La unidad de negocio: la de obra trae la suya, la vehicular la toma de
     * su unidad.
     */
    public function businessUnitName(): ?string
    {
        return $this->isConstruction() ? $this->businessUnit?->name : $this->unit?->businessUnit?->name;
    }

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
     * Cómo va la vigencia del periodo: corre del inicio del primer semestre al
     * cierre del segundo, salvo que se haya pedido cancelarla o ya esté
     * cancelada. Es el estado de la tabla de pólizas y fianzas.
     */
    public function coverageStatus(): string
    {
        return CoverageStatus::of($this->second_payment_ends_on, $this->cancelled_on, $this->cancellation_requested_on);
    }

    /**
     * El primer semestre sin pagar y su fecha límite de pago (`*_due_on`), que
     * pone la aseguradora y no es el cierre del semestre. 'overdue' si ya pasó,
     * 'pending' si aún está a tiempo o no tiene fecha. Null con los dos pagados o cancelada.
     *
     * @return array{payment: string, due_on: ?Carbon, status: string}|null
     */
    public function nextDue(): ?array
    {
        $payment = collect(self::PAYMENTS)->first(fn (string $payment) => ! $this->isPaid($payment));

        // Cancelada ya no se paga lo que faltaba.
        if (! $payment || $this->coverageStatus() === CoverageStatus::CANCELLED) {
            return null;
        }

        $dueOn = $this->{"{$payment}_payment_due_on"};

        return [
            'payment' => $payment,
            'due_on' => $dueOn,
            'status' => $dueOn && $dueOn->lt(today()) ? 'overdue' : 'pending',
        ];
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
            'first_payment_due_on' => 'date',
            'first_payment_paid_at' => 'date',
            'second_payment_starts_on' => 'date',
            'second_payment_ends_on' => 'date',
            'second_payment_due_on' => 'date',
            'second_payment_paid_at' => 'date',
            'endorsement' => 'boolean',
            'cancellation_requested_on' => 'date',
            'cancelled_on' => 'date',
            'first_payment_amount' => 'decimal:2',
            'second_payment_amount' => 'decimal:2',
        ];
    }

    /** Solo en las vehiculares. */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /** Solo en las de obra: la vehicular usa la de su unidad. */
    public function businessUnit(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class);
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
