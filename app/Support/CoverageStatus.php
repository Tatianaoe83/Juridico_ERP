<?php

namespace App\Support;

use App\Models\Unit;
use Illuminate\Support\Carbon;

/**
 * Cómo va una cobertura (póliza o fianza) según su vigencia. Es lo que se
 * cuenta en las tarjetas y se pinta en la columna Estado.
 */
final class CoverageStatus
{
    public const ACTIVE = 'active';

    /** Termina dentro de la ventana de aviso. */
    public const EXPIRING = 'expiring';

    public const EXPIRED = 'expired';

    /** Se pidió cancelarla y aún no queda cancelada. */
    public const CANCELLING = 'cancelling';

    public const CANCELLED = 'cancelled';

    /** Sin fecha de fin no hay vigencia que vigilar. */
    public const UNKNOWN = 'unknown';

    /** Los días de aviso son los mismos que en flotillas. */
    public const WARNING_DAYS = Unit::WARNING_DAYS;

    public static function of(?Carbon $validUntil, ?Carbon $cancelledOn = null, ?Carbon $cancellationRequestedOn = null): string
    {
        $today = today();

        return match (true) {
            $cancelledOn !== null && $cancelledOn->lte($today) => self::CANCELLED,
            $validUntil === null => self::UNKNOWN,
            $validUntil->lt($today) => self::EXPIRED,
            $cancellationRequestedOn !== null => self::CANCELLING,
            $validUntil->lte($today->copy()->addDays(self::WARNING_DAYS)) => self::EXPIRING,
            default => self::ACTIVE,
        };
    }
}
