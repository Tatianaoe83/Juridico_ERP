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
    /** Duración del evento cuando la vigencia trae hora. */
    private const DURATION_MINUTES = 60;

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
            $event = $this->events->sync($actor, $license->calendar_event_id, $this->event($license, $actor), notify: false);
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
     * Lo que se ve en Outlook. El cuerpo repite empresa, autoridad y quién dio
     * de alta el registro: quien abre el evento no tiene por qué entrar al ERP
     * para saber de qué va.
     *
     * Los invitados no van aquí: CalendarEvents suma a quienes tienen el
     * calendario compartido.
     *
     * @return array<string, mixed>
     */
    private function event(License $license, User $actor): array
    {
        $start = $license->expiresAt();
        $allDay = $license->isAllDay();

        $lines = array_filter([
            $license->company ? "Empresa: {$license->company}" : null,
            $license->authority ? "Autoridad: {$license->authority}" : null,
            'Registrado por: '.($license->creator?->name ?? $actor->name),
            'Alta: '.$license->created_at?->format('d/m/Y H:i'),
            $license->comments ? "\n{$license->comments}" : null,
        ]);

        return [
            'title' => $license->name,
            'description' => implode("\n", $lines),
            // Outlook pone la autoridad en «Ubicación» y confunde: va en la descripción.
            'location' => null,
            'all_day' => $allDay,
            'start' => $start,
            'end' => $allDay ? $start->copy() : $start->copy()->addMinutes(self::DURATION_MINUTES),
            // La alerta la lanza Outlook en cada buzón; el servidor no manda nada.
            'reminder_minutes' => $license->notification?->minutes_before,
        ];
    }
}
