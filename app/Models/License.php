<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Licencia, permiso o trámite que la empresa debe mantener vigente.
 */
class License extends Model
{
    /** Los mismos valores que el enum de la columna `status`. */
    public const STATUSES = ['active', 'expiring', 'expired', 'in_progress'];

    /** Días antes de la vigencia en que pasa a «por vencer». */
    public const WARNING_DAYS = 30;

    protected $guarded = [];

    /**
     * El estado no lo elige nadie: sale de la vigencia. Sin fecha todavía es
     * un trámite en curso; con fecha, depende de cuánto le falta.
     */
    public static function statusFor(?CarbonInterface $validUntil): string
    {
        if ($validUntil === null) {
            return 'in_progress';
        }

        $today = today();

        if ($validUntil->lt($today)) {
            return 'expired';
        }

        return $validUntil->lte($today->copy()->addDays(self::WARNING_DAYS)) ? 'expiring' : 'active';
    }

    /**
     * Vigencia con hora, para el calendario. Sin hora devuelve el inicio del
     * día y el evento se marca de todo el día.
     */
    public function expiresAt(): ?Carbon
    {
        if ($this->valid_until === null) {
            return null;
        }

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
