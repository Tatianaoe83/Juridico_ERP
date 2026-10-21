<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Licencia, permiso o trámite que la empresa debe mantener vigente.
 */
class License extends Model
{
    /**
     * Los estados posibles. No se guardan: salen de la vigencia cada vez que
     * se consultan, así que nunca se quedan atrás de la fecha.
     */
    public const STATUSES = ['active', 'expiring', 'expired'];

    /** Días antes de la vigencia en que pasa a «por vencer». */
    public const WARNING_DAYS = 30;

    protected $guarded = [];

    /**
     * El estado no lo elige nadie: sale de la vigencia y de cuánto le falta.
     */
    public static function statusFor(CarbonInterface $validUntil): string
    {
        $today = today();

        if ($validUntil->lt($today)) {
            return 'expired';
        }

        return $validUntil->lte($today->copy()->addDays(self::WARNING_DAYS)) ? 'expiring' : 'active';
    }

    /** El estado de hoy, con la misma regla que statusFor(). */
    public function status(): string
    {
        return self::statusFor($this->valid_until);
    }

    /**
     * Filtra por estado con rangos de la vigencia: los mismos cortes que
     * statusFor(), pero en SQL para poder paginar.
     */
    public function scopeInStatus(Builder $query, string $status): void
    {
        $today = today();
        $limit = $today->copy()->addDays(self::WARNING_DAYS);

        match ($status) {
            'expired' => $query->whereDate('valid_until', '<', $today),
            'expiring' => $query->whereDate('valid_until', '>=', $today)->whereDate('valid_until', '<=', $limit),
            'active' => $query->whereDate('valid_until', '>', $limit),
        };
    }

    /**
     * Cuántas hay en cada estado, de todo el catálogo.
     *
     * @return array<string, int>
     */
    public static function statusCounts(): array
    {
        $today = today()->toDateString();
        $limit = today()->addDays(self::WARNING_DAYS)->toDateString();

        $counts = self::query()
            ->selectRaw(
                "case when valid_until < ? then 'expired' when valid_until <= ? then 'expiring' else 'active' end as state, count(*) as total",
                [$today, $limit],
            )
            ->groupBy('state')
            ->pluck('total', 'state');

        return collect(self::STATUSES)->mapWithKeys(fn ($s) => [$s => (int) ($counts[$s] ?? 0)])->all();
    }

    protected function casts(): array
    {
        return [
            'valid_until' => 'date',
            'reminders_enabled' => 'boolean',
        ];
    }

    /** La empresa: una unidad de negocio del catálogo. */
    public function businessUnit(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class);
    }

    /** Quién dio de alta el registro. */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
