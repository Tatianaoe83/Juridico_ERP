<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Calendar\DeleteEventRequest;
use App\Http\Requests\Calendar\StoreEventRequest;
use App\Http\Requests\Calendar\UpdateEventRequest;
use App\Models\User;
use App\Notifications\CalendarEventNotification;
use App\Services\Microsoft\CalendarOwner;
use App\Services\Microsoft\MicrosoftGraph;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Alta, edición y baja de eventos en Outlook: en el buzón general (modo
 * aplicación) o en el calendario del propio usuario (delegado).
 * No hay copia local: Graph es la única fuente. Cada acción avisa por correo.
 */
class EventController extends Controller
{
    /** Ventana de la lista de próximos eventos, en días. */
    private const UPCOMING_DAYS = 30;

    public function __construct(private readonly MicrosoftGraph $graph) {}

    public function index(Request $request): Response
    {
        $calendar = $request->user()->calendarOwner();
        $canWrite = (bool) $calendar?->canWriteCalendar();

        $upcoming = [];
        $loadError = null;
        $editing = null;
        $sharedWith = [];

        // Sin permiso de escritura no tiene sentido leer: la página solo va a
        // pedir que reconecte la cuenta.
        if ($canWrite) {
            try {
                $upcoming = $this->graph->calendarView(
                    $calendar,
                    now()->startOfDay(),
                    now()->addDays(self::UPCOMING_DAYS)->endOfDay(),
                );
            } catch (Throwable $e) {
                report($e);
                $loadError = 'No se pudieron leer los próximos eventos de Outlook.';
            }

            // Quiénes tienen acceso al calendario. Falla en silencio: no poder
            // leer los permisos no debe tumbar la página de eventos.
            try {
                $sharedWith = $this->graph->calendarPermissions($calendar);
            } catch (Throwable $e) {
                report($e);
            }

            // ?event= llega desde el botón de editar del calendario. Se busca
            // aparte porque puede caer fuera de la ventana de próximos días.
            if ($request->filled('event')) {
                try {
                    $editing = $this->graph->findEvent($calendar, $request->string('event')->value());
                } catch (Throwable $e) {
                    report($e);
                    $loadError = 'Ese evento ya no existe en Outlook.';
                }
            }
        }

        return Inertia::render('Events/Index', [
            'mode' => config('services.microsoft.mode', 'delegated'),
            'connected' => (bool) $calendar,
            'canWrite' => $canWrite,
            'timezone' => config('app.timezone'),
            'upcoming' => $upcoming,
            'editing' => $editing,
            'sharedWith' => $sharedWith,
            // Sin calendario dedicado, compartir afectaría la agenda personal.
            'dedicatedCalendar' => (bool) $calendar?->hasDedicatedCalendar(),
            'loadError' => $loadError,
        ]);
    }

    public function store(StoreEventRequest $request): RedirectResponse
    {
        $calendar = $request->user()->calendarOwner();

        try {
            $event = $this->graph->createEvent(
                $calendar,
                $this->withSharedGuests($calendar, $request->event(), $request->boolean('invite_shared', true)),
            );
        } catch (Throwable $e) {
            return $this->failed($e);
        }

        $this->announce($request->user(), $calendar, CalendarEventNotification::CREATED, $event);

        return back()->with('success', 'Evento creado. Te llegó un correo de confirmación.');
    }

    public function update(UpdateEventRequest $request): RedirectResponse
    {
        $calendar = $request->user()->calendarOwner();
        $eventId = $request->string('event_id')->value();

        try {
            // Se lee antes del PATCH para poder contar qué cambió: después ya
            // no hay forma de saber cómo estaba.
            $previous = $this->graph->findEvent($calendar, $eventId);
            $event = $this->graph->updateEvent(
                $calendar,
                $eventId,
                $this->withSharedGuests($calendar, $request->event(), $request->boolean('invite_shared', true)),
            );
        } catch (Throwable $e) {
            return $this->failed($e);
        }

        $this->announce($request->user(), $calendar, CalendarEventNotification::UPDATED, $event, $previous);

        return back()->with('success', 'Evento actualizado. Te llegó un correo de confirmación.');
    }

    public function destroy(DeleteEventRequest $request): RedirectResponse
    {
        $calendar = $request->user()->calendarOwner();
        $eventId = $request->string('event_id')->value();

        try {
            // Los datos se leen antes: una vez borrado Graph ya no los da.
            $event = $this->graph->findEvent($calendar, $eventId);
            $this->graph->deleteEvent($calendar, $eventId);
        } catch (Throwable $e) {
            return $this->failed($e);
        }

        $this->announce($request->user(), $calendar, CalendarEventNotification::DELETED, $event);

        return back()->with('success', 'Evento eliminado. Te llegó un correo de confirmación.');
    }

    /**
     * Manda el aviso a quien hizo el cambio y a quienes tienen acceso al
     * calendario.
     *
     * La confirmación va al correo de su cuenta de Microsoft si la tiene, que
     * es el que asocia con su agenda (puede no ser el de su cuenta local).
     * Nunca al buzón general: en modo aplicación es el organizador de todo y
     * se llenaría de avisos que nadie lee.
     *
     * @param  array<string, mixed>  $event
     * @param  array<string, mixed>|null  $previous  Cómo estaba antes de editarlo.
     */
    private function announce(
        User $actor,
        CalendarOwner $calendar,
        string $action,
        array $event,
        ?array $previous = null,
    ): void {
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

        // El calendario cachea por rango; sin esto el cambio no se ve.
        $calendar->bumpCalendarVersion();
    }

    /**
     * Suma a los invitados del evento a quienes tienen acceso al calendario.
     *
     * Con eso Outlook les manda invitación, actualización y cancelación de
     * forma nativa, y el evento les aparece en su agenda propia con RSVP.
     *
     * Va activo por defecto —incluso si la petición no trae el campo— porque
     * los avisos nativos son ahora el canal principal. Se puede desmarcar por
     * evento cuando sea informativo y no amerite pedir confirmación.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withSharedGuests(CalendarOwner $calendar, array $data, bool $invite): array
    {
        if (! $invite) {
            return $data;
        }

        try {
            $shared = $this->graph->calendarPermissions($calendar);
        } catch (Throwable $e) {
            // Se crea el evento igual, solo que sin los compartidos.
            report($e);

            return $data;
        }

        $emails = collect($shared)
            ->pluck('email')
            ->filter()
            ->map(fn (string $email) => mb_strtolower($email))
            ->filter(fn (string $email) => (bool) filter_var($email, FILTER_VALIDATE_EMAIL))
            // El organizador no puede figurar como invitado de su propio evento.
            ->reject(fn (string $email) => $email === mb_strtolower($calendar->mailboxEmail()));

        $data['attendees'] = collect($data['attendees'] ?? [])
            ->merge($emails)
            ->unique()
            ->values()
            ->all();

        return $data;
    }

    /**
     * A quién más avisar: los que tienen acceso al calendario, menos el dueño
     * (ya va aparte) y menos los invitados del evento, que reciben la
     * invitación nativa de Outlook y tendrían dos correos por lo mismo.
     *
     * @param  array<string, mixed>  $event
     * @return array<int, string>
     */
    private function sharedAudience(CalendarOwner $calendar, array $event): array
    {
        try {
            $shared = $this->graph->calendarPermissions($calendar);
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
            ->pluck('email')
            ->filter()
            ->map(fn (string $email) => mb_strtolower($email))
            // Outlook mete entradas sin correo real, como el acceso público.
            ->filter(fn (string $email) => (bool) filter_var($email, FILTER_VALIDATE_EMAIL))
            ->reject(fn (string $email) => $email === mb_strtolower($calendar->mailboxEmail()))
            ->reject(fn (string $email) => in_array($email, $invited, true))
            ->unique()
            ->values()
            ->all();
    }

    private function failed(Throwable $e): RedirectResponse
    {
        report($e);

        $application = config('services.microsoft.mode') === 'application';

        $message = $e instanceof RequestException
            ? match ($e->response->status()) {
                401 => $application
                    ? 'El token de la aplicación fue rechazado. Revisa el secreto en el .env.'
                    : 'Tu sesión con Microsoft caducó. Vuelve a conectar la cuenta.',
                403 => $application
                    ? 'La aplicación no tiene Calendars.ReadWrite sobre el buzón general. Falta el consentimiento de administrador o la política de acceso de Exchange lo bloquea.'
                    : 'Falta el permiso Calendars.ReadWrite. Reconecta tu cuenta para concederlo.',
                404 => 'El evento ya no existe en Outlook.',
                default => 'Microsoft Graph rechazó la operación ('.$e->response->status().').',
            }
            : 'No se pudo completar la operación en Outlook.';

        return back()->with('error', $message);
    }
}
