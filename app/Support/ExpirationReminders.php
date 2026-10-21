<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Cuándo se manda el recordatorio recurrente por correo, contado en días antes
 * del vencimiento: desde dos meses antes cada 15 días, desde el mes cada 7,
 * desde los 5 días cada 2, y el mismo día.
 *
 * Lo comparten todos los módulos que mandan recordatorios.
 */
class ExpirationReminders
{
    public const DAYS = [60, 45, 30, 23, 16, 9, 5, 3, 1, 0];

    /**
     * Las fechas de vencimiento que hoy tocan recordatorio.
     *
     * @return list<string>
     */
    public static function dueDates(?Carbon $today = null): array
    {
        $today ??= today();

        return array_map(fn (int $days) => $today->copy()->addDays($days)->toDateString(), self::DAYS);
    }

    /**
     * A quién se manda: correos fijos, por ahora uno solo.
     *
     * @return list<string>
     */
    public static function recipients(): array
    {
        return config('mail.reminders.recipients', []);
    }
}
