<?php

namespace App\Services;

use App\Models\License;
use App\Models\User;
use App\Services\Microsoft\CalendarOwner;
use App\Services\Microsoft\MicrosoftGraph;
use Throwable;

/**
 * Refleja cada licencia en el calendario de Outlook: el día que vence queda
 * agendado sin que nadie lo capture a mano.
 *
 * Nada de esto puede tumbar el alta: si Graph falla o la cuenta no está
 * vinculada, la licencia se guarda igual y el evento sencillamente no se crea.
 * Por eso los métodos devuelven bool en vez de lanzar.
 */
class LicenseCalendar
{
    /** Duración del evento cuando la vigencia trae hora. */
    private const DURATION_MINUTES = 60;

    public function __construct(private readonly MicrosoftGraph $graph) {}

    /**
     * Crea el evento o mueve el que ya existía. Sin vigencia no hay nada que
     * agendar: si había evento, se quita.
     */
    public function sync(License $license, User $actor): bool
    {
        $calendar = $actor->calendarOwner();

        if (! $calendar?->canWriteCalendar()) {
            return false;
        }

        if ($license->valid_until === null) {
            return $this->forget($license, $actor);
        }

        try {
            $data = $this->event($license, $actor, $this->guests($license, $calendar));

            // Si el evento se borró desde Outlook, el PATCH falla: se vuelve a
            // crear en vez de dejar la licencia sin nada en el calendario.
            $event = $license->calendar_event_id
                ? $this->update($calendar, $license->calendar_event_id, $data)
                : $this->graph->createEvent($calendar, $data);
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
        $calendar = $actor->calendarOwner();

        if (! $license->calendar_event_id || ! $calendar?->canWriteCalendar()) {
            return false;
        }

        try {
            $this->graph->deleteEvent($calendar, $license->calendar_event_id);
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
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function update(CalendarOwner $calendar, string $eventId, array $data): array
    {
        try {
            return $this->graph->updateEvent($calendar, $eventId, $data);
        } catch (Throwable $e) {
            report($e);

            return $this->graph->createEvent($calendar, $data);
        }
    }

    /**
     * Lo que se ve en Outlook. El cuerpo repite empresa, autoridad y quién dio
     * de alta el registro: quien abre el evento no tiene por qué entrar al ERP
     * para saber de qué va.
     *
     * @param  list<string>  $guests
     * @return array<string, mixed>
     */
    private function event(License $license, User $actor, array $guests): array
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
            'title' => "Vence: {$license->name}",
            'description' => implode("\n", $lines),
            'location' => $license->authority,
            'all_day' => $allDay,
            'start' => $start,
            'end' => $allDay ? $start->copy() : $start->copy()->addMinutes(self::DURATION_MINUTES),
            'attendees' => $guests,
        ];
    }

    /**
     * A quién invita Outlook. Dos listas que se suman:
     *
     *  - Con quién está compartido el calendario: los mismos que ya ven todo
     *    el panorama, igual que en los eventos normales.
     *  - Los correos del recordatorio de esta licencia: a quien le interesa
     *    este vencimiento en concreto, aunque no tenga el calendario.
     *
     * Con la invitación no hace falta aceptar ningún calendario compartido: el
     * evento cae en la bandeja y en la agenda propia, con Aceptar y Rechazar.
     *
     * @return list<string>
     */
    private function guests(License $license, CalendarOwner $calendar): array
    {
        try {
            $shared = collect($this->graph->calendarPermissions($calendar))->pluck('email');
        } catch (Throwable $e) {
            // Se agenda igual, solo que sin los del calendario compartido.
            report($e);

            $shared = collect();
        }

        return $shared
            ->merge($license->notification?->recipients ?? [])
            ->filter()
            ->map(fn (string $email) => mb_strtolower(trim($email)))
            ->filter(fn (string $email) => (bool) filter_var($email, FILTER_VALIDATE_EMAIL))
            // El organizador no puede figurar como invitado de su propio evento.
            ->reject(fn (string $email) => $email === mb_strtolower($calendar->mailboxEmail()))
            ->unique()
            ->values()
            ->all();
    }
}
