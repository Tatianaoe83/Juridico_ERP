<?php

namespace App\Services;

use App\Models\License;
use App\Models\User;
use App\Services\Microsoft\CalendarOwner;
use App\Services\Microsoft\MicrosoftGraph;
use Illuminate\Support\Facades\Cache;
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

        try {
            $data = $this->event($license, $actor, $this->sharedWith($calendar));

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
            'title' => $license->name,
            'description' => implode("\n", $lines),
            // Outlook pone la autoridad en «Ubicación» y confunde: va en la descripción.
            'location' => null,
            'all_day' => $allDay,
            'start' => $start,
            'end' => $allDay ? $start->copy() : $start->copy()->addMinutes(self::DURATION_MINUTES),
            'attendees' => $guests,
            // La alerta la lanza Outlook en cada buzón; el servidor no manda nada.
            'reminder_minutes' => $license->notification?->minutes_before,
        ];
    }

    /**
     * A quién invita Outlook: los mismos con los que está compartido el
     * calendario. No hay lista aparte que mantener.
     *
     * Con la invitación nadie tiene que aceptar un calendario compartido: el
     * evento cae en la bandeja y en la agenda propia, con Aceptar y Rechazar.
     *
     * La lista la manda Outlook, así que se cachea un rato: se consulta al
     * pintar la tabla de licencias y en cada guardado, y no cambia de un
     * minuto a otro.
     *
     * @return list<string>
     */
    public function sharedWith(?CalendarOwner $calendar): array
    {
        if (! $calendar?->canWriteCalendar()) {
            return [];
        }

        return Cache::remember(
            'license-calendar-shared:'.mb_strtolower($calendar->mailboxEmail()),
            now()->addMinutes(5),
            function () use ($calendar) {
                try {
                    $shared = collect($this->graph->calendarPermissions($calendar))->pluck('email');
                } catch (Throwable $e) {
                    // Sin la lista se agenda igual, solo que sin invitados.
                    report($e);

                    return [];
                }

                return $shared
                    ->filter()
                    ->map(fn (string $email) => mb_strtolower(trim($email)))
                    ->filter(fn (string $email) => (bool) filter_var($email, FILTER_VALIDATE_EMAIL))
                    // El organizador no puede figurar como invitado de su propio evento.
                    ->reject(fn (string $email) => $email === mb_strtolower($calendar->mailboxEmail()))
                    ->unique()
                    ->values()
                    ->all();
            },
        );
    }
}
