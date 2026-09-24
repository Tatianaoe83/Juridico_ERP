<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Unidad de la flotilla: el vehículo. La póliza y sus pagos van por periodo
 * en UnitPolicy, porque se renuevan cada año y la unidad sigue siendo la misma.
 */
class Unit extends Model
{
    /** Los mismos valores que el enum de la columna `status`. */
    public const STATUSES = ['active', 'maintenance', 'inactive'];

    /** Campos que se guardan siempre en mayúsculas. */
    public const UPPERCASE = ['brand', 'model', 'plate', 'responsible'];

    /** Días antes del pago en que la unidad entra en «por vencer». */
    public const WARNING_DAYS = 30;

    protected $guarded = [];

    /** Si el siguiente pago sin cubrir del periodo vigente cae en la ventana de aviso. */
    public function paymentDueSoon(): bool
    {
        $next = $this->currentPolicy?->nextPaymentDate();

        return $next !== null && $next->lte(today()->addDays(self::WARNING_DAYS));
    }

    protected function casts(): array
    {
        return [
            'usa_canada_endorsement' => 'boolean',
        ];
    }

    /** Todos sus periodos de póliza, el más reciente primero. */
    public function policies(): HasMany
    {
        return $this->hasMany(UnitPolicy::class)
            ->orderByDesc('first_payment_starts_on')
            ->orderByDesc('id');
    }

    /** El periodo vigente: el más reciente. */
    public function currentPolicy(): HasOne
    {
        return $this->hasOne(UnitPolicy::class)->ofMany(
            ['first_payment_starts_on' => 'max', 'id' => 'max'],
        );
    }

    /** A qué unidad de negocio pertenece. */
    public function businessUnit(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class);
    }

    /** Quién dio de alta el registro. */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Avisos previos agendados en Outlook: una semana y dos días antes. */
    public function reminders(): HasMany
    {
        return $this->hasMany(UnitReminder::class);
    }

    /** Todos sus archivos, de todos los periodos. */
    public function evidences(): HasMany
    {
        return $this->hasMany(UnitEvidence::class);
    }
}
