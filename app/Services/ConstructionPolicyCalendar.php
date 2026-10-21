<?php

namespace App\Services;

use App\Models\ConstructionPolicy;
use App\Models\User;
use Throwable;

/**
 * Refleja cada póliza de obra en el calendario de Outlook: el fin de su
 * vigencia queda agendado y un endoso lo recorre al nuevo fin.
 *
 * Aquí solo se arma el evento; cómo se inserta lo resuelve CalendarEvents.
 *
 * Nada de esto puede tumbar el alta: si Graph falla o la cuenta no está
 * vinculada, la póliza se guarda igual y el evento sencillamente no se crea.
 * Por eso los métodos devuelven bool en vez de lanzar.
 */
class ConstructionPolicyCalendar
{
    /**
     * Recordatorio de Outlook, en minutos antes del evento: 0 = el mismo día,
     * al empezar (medianoche, porque es de todo el día). Igual que en las
     * fianzas; los avisos con anticipación irán por correo.
     */
    private const REMINDER_MINUTES = 0;

    public function __construct(private readonly CalendarEvents $events) {}

    /** Crea el evento o mueve el que ya existía. */
    public function sync(ConstructionPolicy $policy, User $actor): bool
    {
        if (! $this->events->available($actor)) {
            return false;
        }

        try {
            // Sin correo propio: la invitación nativa de Outlook ya avisa a
            // los compartidos.
            $event = $this->events->sync($actor, $policy->calendar_event_id, $this->event($policy), notify: false);
        } catch (Throwable $e) {
            report($e);

            return false;
        }

        $policy->forceFill(['calendar_event_id' => $event['id']])->save();

        return true;
    }

    /** Quita el evento de Outlook, si lo hay. */
    public function forget(ConstructionPolicy $policy, User $actor): bool
    {
        if (! $policy->calendar_event_id || ! $this->events->available($actor)) {
            return false;
        }

        try {
            $this->events->delete($actor, $policy->calendar_event_id, notify: false);
        } catch (Throwable $e) {
            report($e);

            return false;
        }

        // El registro puede venir en camino a borrarse: solo se limpia si sigue.
        if ($policy->exists) {
            $policy->forceFill(['calendar_event_id' => null])->save();
        }

        return true;
    }

    /**
     * Lo que se ve en Outlook. De todo el día: es el día que se acaba la
     * vigencia, no un horario de nada.
     *
     * @return array<string, mixed>
     */
    private function event(ConstructionPolicy $policy): array
    {
        $policy->loadMissing('project');

        $day = $policy->valid_until->copy()->startOfDay();

        return [
            'title' => collect(['Fin de vigencia', "Póliza {$policy->policy}", $policy->project?->name])->filter()->implode(' · '),
            // Solo el título: el detalle está en el sistema.
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
