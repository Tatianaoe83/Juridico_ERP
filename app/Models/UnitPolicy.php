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
 * La de maquinaria es distinta: cubre varias máquinas, tiene un solo pago y
 * su vigencia se captura completa. Guarda el inicio en first_payment_starts_on,
 * el fin en second_payment_ends_on y el pago en first_payment_*.
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

    public const MACHINERY = 'machinery';

    public const KINDS = [self::VEHICLE, self::CONSTRUCTION, self::MACHINERY];

    /**
     * Las coberturas de cada tipo, las mismas que el enum de la columna
     * `coverage`. Vehicular: responsabilidad civil, limitada, amplia y amplia
     * plus, igual para vehículos y maquinaria. Obra: obra civil, responsabilidad
     * civil de construcción, montaje y maquinaria y equipo. Maquinaria: EC
     * (equipo de contratistas) y RC (responsabilidad civil).
     */
    public const COVERAGES = [
        self::VEHICLE => ['civil_liability', 'limited', 'broad', 'broad_plus'],
        self::CONSTRUCTION => ['civil_works', 'construction_liability', 'erection', 'machinery_equipment'],
        self::MACHINERY => ['contractors_equipment', 'civil_liability'],
    ];

    /**
     * Origen de la póliza: la empresarial la contrata la empresa; la del
     * proveedor es la que lleva beneficiario preferente, con la aseguradora
     * que elige quien vende, financia o arrienda la unidad. La de maquinaria
     * no lleva pagos y puede cubrir varias máquinas; la vehicular, un pago
     * único anual en las columnas del primero.
     */
    public const CORPORATE = 'corporate';

    public const SUPPLIER = 'supplier';

    public const SOURCES = [self::CORPORATE, self::SUPPLIER];

    /** Monedas en las que se puede pagar una póliza de maquinaria. */
    public const CURRENCIES = ['MXN', 'USD'];

    /**
     * Moneda sugerida por cobertura: EC suele ir en dólares y RC en pesos, pero
     * depende del contrato, así que solo es el valor inicial y se puede cambiar.
     */
    public const MACHINERY_CURRENCIES = [
        'contractors_equipment' => 'USD',
        'civil_liability' => 'MXN',
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

    public function isMachinery(): bool
    {
        return $this->kind === self::MACHINERY;
    }

    /** Si la impuso el proveedor: sin semestres, solo su vigencia (y en la vehicular, un pago anual). */
    public function isFromSupplier(): bool
    {
        return $this->source === self::SUPPLIER;
    }

    /**
     * Las fechas de los dos semestres a partir del inicio de la vigencia: seis
     * meses exactos cada uno. Si el día no existe en el mes (31 de agosto →
     * febrero) se queda en el último.
     *
     * @return array<string, Carbon>
     */
    public static function semestersFrom(Carbon $start): array
    {
        $middle = $start->copy()->addMonthsNoOverflow(self::SEMESTER_MONTHS);

        return [
            'first_payment_starts_on' => $start->copy(),
            'first_payment_ends_on' => $middle,
            'second_payment_starts_on' => $middle->copy(),
            'second_payment_ends_on' => $middle->copy()->addMonthsNoOverflow(self::SEMESTER_MONTHS),
        ];
    }

    /**
     * La unidad de negocio: la de obra y la de maquinaria traen la suya; la
     * vehicular la toma de su unidad.
     */
    public function businessUnitName(): ?string
    {
        return $this->kind === self::VEHICLE ? $this->unit?->businessUnit?->name : $this->businessUnit?->name;
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
            'financing' => 'boolean',
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

    /** Solo en las de maquinaria: las máquinas que cubre. */
    public function machines(): HasMany
    {
        return $this->hasMany(Unit::class, 'unit_policy_id')->orderBy('economic_number')->orderBy('id');
    }

    /** En las de obra y maquinaria: la vehicular usa la de su unidad. */
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
