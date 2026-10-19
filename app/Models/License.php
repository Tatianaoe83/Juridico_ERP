<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

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

    /**
     * Minutos antes del evento que acepta el recordatorio de Outlook. Son los
     * mismos presets que ofrece Outlook, en las dos variantes: con hora se
     * cuentan desde ella; sin hora el evento arranca a medianoche, así que
     * «el día anterior a las 9:00» son 900 minutos.
     *
     * La lista existe para no dejar pasar cualquier número: el selector solo
     * muestra estos y el servidor no acepta otros.
     */
    public const REMINDER_MINUTES = [0, 5, 15, 30, 60, 120, 420, 720, 900, 1440, 2340, 2880, 9540, 10080];

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

    /**
     * Vigencia con hora, para el calendario. Sin hora devuelve el inicio del
     * día y el evento se marca de todo el día.
     */
    public function expiresAt(): Carbon
    {
        $moment = $this->valid_until->copy()->startOfDay();

        if ($this->valid_time === null) {
            return $moment;
        }

        [$hour, $minute] = explode(':', substr((string) $this->valid_time, 0, 5));

        return $moment->setTime((int) $hour, (int) $minute);
    }

    /** Sin hora, la vigencia es un día entero en el calendario. */
    public function isAllDay(): bool
    {
        return $this->valid_time === null;
    }

    protected function casts(): array
    {
        return [
            'valid_until' => 'date',
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

    public function notification(): HasOne
    {
        return $this->hasOne(LicenseNotification::class);
    }
}
