<?php

namespace App\Services;

use App\Models\License;
use App\Models\User;
use Throwable;

/**
 * Refleja cada licencia en el calendario de Outlook: el día que vence queda
 * agendado sin que nadie lo capture a mano.
 *
 * Aquí solo se arma el evento; cómo se inserta lo resuelve CalendarEvents.
 *
 * Nada de esto puede tumbar el alta: si Graph falla o la cuenta no está
 * vinculada, la licencia se guarda igual y el evento sencillamente no se crea.
 * Por eso los métodos devuelven bool en vez de lanzar.
 */
class LicenseCalendar
{
    /**
     * Recordatorio de Outlook, en minutos antes del evento: 0 = el mismo día,
     * al empezar (medianoche, porque es de todo el día). Igual que en pólizas
     * y fianzas; los avisos con anticipación irán por correo.
     */
    private const REMINDER_MINUTES = 0;

    public function __construct(private readonly CalendarEvents $events) {}

    /** Crea el evento o mueve el que ya existía. */
    public function sync(License $license, User $actor): bool
    {
        if (! $this->events->available($actor)) {
            return false;
        }

        try {
            // Sin correo propio: la invitación nativa de Outlook ya avisa a
            // los compartidos.
            $event = $this->events->sync($actor, $license->calendar_event_id, $this->event($license), notify: false);
        } catch (Throwable $e) {
            report($e);

            return false;
        }

        $license->forceFill(['calendar_event_id' => $event['id']])->save();

        return true;
    }

    /** Quita el evento de Outlook, si lo hay. */
    public function forget(License $license, User $actor): bool
    {
        if (! $license->calendar_event_id || ! $this->events->available($actor)) {
            return false;
        }

        try {
            $this->events->delete($actor, $license->calendar_event_id, notify: false);
        } catch (Throwable $e) {
            report($e);

            return false;
        }

        // El registro puede venir en camino a borrarse: solo se limpia si sigue.
        if ($license->exists) {
            $license->forceFill(['calendar_event_id' => null])->save();
        }

        return true;
    }

    /**
     * Lo que se ve en Outlook: solo el título, sin descripción. El detalle
     * está en el sistema.
     *
     * Los invitados no van aquí: CalendarEvents suma a quienes tienen el
     * calendario compartido.
     *
     * @return array<string, mixed>
     */
    private function event(License $license): array
    {
        // Solo importa el día: el evento es de todo el día.
        $day = $license->valid_until->copy()->startOfDay();

        return [
            // Mismo formato que pólizas y fianzas: qué pasa · qué es.
            'title' => "Fin de vigencia · {$license->name}",
            'description' => null,
            'location' => null,
            'all_day' => true,
            'start' => $day,
            'end' => $day->copy(),
            // La alerta la lanza Outlook en cada buzón; el servidor no manda nada.
            'reminder_minutes' => self::REMINDER_MINUTES,
        ];
    }
}
