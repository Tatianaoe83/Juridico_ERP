<?php

namespace App\Services;

use App\Models\Bond;
use App\Models\User;
use Throwable;

/**
 * Refleja cada fianza en el calendario de Outlook: el fin de su vigencia queda
 * agendado sin que nadie lo capture a mano.
 *
 * Aquí solo se arma el evento; cómo se inserta lo resuelve CalendarEvents.
 *
 * Nada de esto puede tumbar el alta: si Graph falla o la cuenta no está
 * vinculada, la fianza se guarda igual y el evento sencillamente no se crea.
 * Por eso los métodos devuelven bool en vez de lanzar.
 */
class BondCalendar
{
    /**
     * Anticipación del recordatorio del evento, en minutos: una semana, igual
     * que en las pólizas. Es el recordatorio propio de Outlook, el del reloj.
     */
    private const REMINDER_MINUTES = 10080;

    public function __construct(private readonly CalendarEvents $events) {}

    /**
     * Crea el evento o mueve el que ya existía. Sin fin de vigencia no hay qué
     * agendar: si había evento, se quita.
     */
    public function sync(Bond $bond, User $actor): bool
    {
        if (! $bond->valid_until) {
            return $this->forget($bond, $actor);
        }

        if (! $this->events->available($actor)) {
            return false;
        }

        try {
            // Sin correo propio: la invitación nativa de Outlook ya avisa a
            // los compartidos.
            $event = $this->events->sync($actor, $bond->calendar_event_id, $this->event($bond), notify: false);
        } catch (Throwable $e) {
            report($e);

            return false;
        }

        $bond->forceFill(['calendar_event_id' => $event['id']])->save();

        return true;
    }

    /** Quita el evento de Outlook, si lo hay. */
    public function forget(Bond $bond, User $actor): bool
    {
        if (! $bond->calendar_event_id || ! $this->events->available($actor)) {
            return false;
        }

        try {
            $this->events->delete($actor, $bond->calendar_event_id, notify: false);
        } catch (Throwable $e) {
            report($e);

            return false;
        }

        // El registro puede venir en camino a borrarse: solo se limpia si sigue.
        if ($bond->exists) {
            $bond->forceFill(['calendar_event_id' => null])->save();
        }

        return true;
    }

    /**
     * Lo que se ve en Outlook. De todo el día: es el día que se acaba la
     * vigencia, no un horario de nada.
     *
     * @return array<string, mixed>
     */
    private function event(Bond $bond): array
    {
        $bond->loadMissing('businessUnit');

        $lines = array_filter([
            "Fianza: {$bond->bond}",
            $bond->bonding_company ? "Afianzadora: {$bond->bonding_company}" : null,
            $bond->beneficiary ? "Beneficiario: {$bond->beneficiary}" : null,
            $bond->related ? "Relativo: {$bond->related}" : null,
            $bond->businessUnit ? "Unidad de negocio: {$bond->businessUnit->name}" : null,
        ]);

        $day = $bond->valid_until->copy()->startOfDay();

        return [
            'title' => collect(['Fin de vigencia', "Fianza {$bond->bond}", $bond->beneficiary])->filter()->implode(' · '),
            'description' => implode("\n", $lines),
            // La unidad de negocio va en la descripción: en «Ubicación» Outlook
            // la muestra como si fuera un lugar.
            'location' => null,
            'all_day' => true,
            'start' => $day,
            'end' => $day->copy(),
            // La alerta la lanza Outlook en cada buzón; el servidor no manda nada.
            'reminder_minutes' => self::REMINDER_MINUTES,
        ];
    }
}
