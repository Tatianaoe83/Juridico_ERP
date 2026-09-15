<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

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

    protected function casts(): array
    {
        return [
            'valid_until' => 'date',
        ];
    }
}
