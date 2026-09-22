<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\CalendarEventNotification;
use App\Services\Microsoft\CalendarOwner;
use App\Services\Microsoft\MicrosoftGraph;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Throwable;

/**
 * Único punto para agendar en Outlook. Cada módulo (calendario, licencias,
 * lo que siga) solo arma el evento que quiere insertar y lo pasa aquí.
 */
class CalendarEvents
{
    /** Cuánto se cachea la lista de con quién está compartido el calendario. */
    private const SHARED_TTL_MINUTES = 5;

    public function __construct(private readonly MicrosoftGraph $graph) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  bool  $inviteShared  Invitar a quienes tienen acceso al calendario.
     * @param  bool  $notify  Mandar el correo de confirmación y aviso.
     * @return array<string, mixed> El evento como lo devuelve Graph (trae el `id`).
     */
    public function create(User $actor, array $data, bool $inviteShared = true, bool $notify = true): array
    {
        $calendar = $this->calendar($actor);

        $event = $this->graph->createEvent($calendar, $this->withSharedGuests($calendar, $data, $inviteShared));

        $this->announce($actor, $calendar, CalendarEventNotification::CREATED, $event, notify: $notify);

        return $event;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function update(User $actor, string $eventId, array $data, bool $inviteShared = true, bool $notify = true): array
    {
        $calendar = $this->calendar($actor);

        // Se lee antes del PATCH para poder contar qué cambió: después ya no
        // hay forma de saber cómo estaba.
        $previous = $notify ? $this->graph->findEvent($calendar, $eventId) : null;

        $event = $this->graph->updateEvent($calendar, $eventId, $this->withSharedGuests($calendar, $data, $inviteShared));

        $this->announce($actor, $calendar, CalendarEventNotification::UPDATED, $event, $previous, $notify);

        return $event;
    }

    /**
     * Crea el evento o actualiza el que ya existía. Para módulos que guardan
     * el id del evento junto a su registro.
     *
     * Si el evento se borró desde Outlook el PATCH falla: se vuelve a crear en
     * vez de dejar el registro sin nada en el calendario.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function sync(User $actor, ?string $eventId, array $data, bool $inviteShared = true, bool $notify = true): array
    {
        if (! $eventId) {
            return $this->create($actor, $data, $inviteShared, $notify);
        }

        try {
            return $this->update($actor, $eventId, $data, $inviteShared, $notify);
        } catch (Throwable $e) {
            report($e);

            return $this->create($actor, $data, $inviteShared, $notify);
        }
    }

    public function delete(User $actor, string $eventId, bool $notify = true): void
    {
        $calendar = $this->calendar($actor);

        // Los datos se leen antes: una vez borrado Graph ya no los da.
        $event = $notify ? $this->graph->findEvent($calendar, $eventId) : [];

        $this->graph->deleteEvent($calendar, $eventId);

        $this->announce($actor, $calendar, CalendarEventNotification::DELETED, $event, notify: $notify);
    }

    /** Si el usuario puede escribir en algún calendario. */
    public function available(User $actor): bool
    {
        return (bool) $actor->calendarOwner()?->canWriteCalendar();
    }

    /**
     * Con quién está compartido el calendario del usuario, para mostrarlo.
     * Sin calendario o si Outlook no responde, lista vacía.
     *
     * @return list<string>
     */
    public function sharedWith(User $actor): array
    {
        $calendar = $actor->calendarOwner();

        if (! $calendar?->canWriteCalendar()) {
            return [];
        }

        try {
            return $this->sharedEmails($calendar);
        } catch (Throwable $e) {
            report($e);

            return [];
        }
    }

    /** Tira la lista cacheada: se llama al compartir o dejar de compartir. */
    public function forgetSharedWith(User $actor): void
    {
        $calendar = $actor->calendarOwner();

        if ($calendar) {
            Cache::forget($this->sharedKey($calendar));
        }
    }

    private function calendar(User $actor): CalendarOwner
    {
        $calendar = $actor->calendarOwner();

        if (! $calendar?->canWriteCalendar()) {
            throw new RuntimeException('El usuario no tiene un calendario de Outlook con permiso de escritura.');
        }

        return $calendar;
    }

    private function announce(
        User $actor,
        CalendarOwner $calendar,
        string $action,
        array $event,
        ?array $previous = null,
        bool $notify = true,
    ): void {
        if ($notify) {
            $confirmation = mb_strtolower($actor->microsoftAccount?->email ?? $actor->email);
            $recipients = [$confirmation, ...$this->sharedAudience($calendar, $event)];

            foreach (array_unique($recipients) as $email) {
                // Un fallo de correo no debe deshacer un cambio que Outlook ya
                // aceptó, ni impedir que los demás destinatarios reciban el suyo.
                try {
                    Notification::route('mail', $email)
                        ->notify(new CalendarEventNotification($action, $event, $previous, $actor->name));
                } catch (Throwable $e) {
                    report($e);
                }
            }
        }

        // El calendario cachea por rango; sin esto el cambio no se ve.
        $calendar->bumpCalendarVersion();
    }

    private function withSharedGuests(CalendarOwner $calendar, array $data, bool $invite): array
    {
        if (! $invite) {
            return $data;
        }

        try {
            $emails = $this->sharedEmails($calendar);
        } catch (Throwable $e) {
            // Se crea el evento igual, solo que sin los compartidos.
            report($e);

            return $data;
        }

        $data['attendees'] = collect($data['attendees'] ?? [])
            ->merge($emails)
            ->unique()
            ->values()
            ->all();

        return $data;
    }

    private function sharedAudience(CalendarOwner $calendar, array $event): array
    {
        try {
            $shared = $this->sharedEmails($calendar);
        } catch (Throwable $e) {
            // Sin la lista se avisa solo al dueño: mejor eso que no avisar.
            report($e);

            return [];
        }

        $invited = collect($event['attendees'] ?? [])
            ->pluck('email')
            ->filter()
            ->map(fn (string $email) => mb_strtolower($email))
            ->all();

        return collect($shared)
            ->reject(fn (string $email) => in_array($email, $invited, true))
            ->values()
            ->all();
    }

    /**
     * La lista la manda Outlook y se consulta en cada guardado y al pintar
     * tablas, así que se cachea un rato. Lanza si Graph falla: cada quien
     * decide qué hacer sin ella.
     *
     * @return list<string>
     */
    private function sharedEmails(CalendarOwner $calendar): array
    {
        return Cache::remember(
            $this->sharedKey($calendar),
            now()->addMinutes(self::SHARED_TTL_MINUTES),
            fn () => collect($this->graph->calendarPermissions($calendar))
                ->pluck('email')
                ->filter()
                ->map(fn (string $email) => mb_strtolower(trim($email)))
                // Outlook mete entradas sin correo real, como el acceso público.
                ->filter(fn (string $email) => (bool) filter_var($email, FILTER_VALIDATE_EMAIL))
                // El organizador no puede figurar como invitado de su propio evento.
                ->reject(fn (string $email) => $email === mb_strtolower($calendar->mailboxEmail()))
                ->unique()
                ->values()
                ->all(),
        );
    }

    private function sharedKey(CalendarOwner $calendar): string
    {
        return 'calendar-shared:'.mb_strtolower($calendar->mailboxEmail());
    }
}
