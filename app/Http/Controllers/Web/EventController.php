<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Calendar\DeleteEventRequest;
use App\Http\Requests\Calendar\StoreEventRequest;
use App\Http\Requests\Calendar\UpdateEventRequest;
use App\Models\MicrosoftAccount;
use App\Notifications\CalendarEventNotification;
use App\Services\Microsoft\MicrosoftGraph;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Alta, edición y baja de eventos en el calendario de Outlook del usuario.
 * No hay copia local: Graph es la única fuente. Cada acción avisa por correo.
 */
class EventController extends Controller
{
    /** Ventana de la lista de próximos eventos, en días. */
    private const UPCOMING_DAYS = 30;

    public function __construct(private readonly MicrosoftGraph $graph) {}

    public function index(Request $request): Response
    {
        $account = $request->user()->microsoftAccount;
        $canWrite = (bool) $account?->canWriteCalendar();

        $upcoming = [];
        $loadError = null;
        $editing = null;
        $sharedWith = [];

        // Sin permiso de escritura no tiene sentido leer: la página solo va a
        // pedir que reconecte la cuenta.
        if ($canWrite) {
            try {
                $upcoming = $this->graph->calendarView(
                    $account,
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
                $sharedWith = $this->graph->calendarPermissions($account);
            } catch (Throwable $e) {
                report($e);
            }

            // ?event= llega desde el botón de editar del calendario. Se busca
            // aparte porque puede caer fuera de la ventana de próximos días.
            if ($request->filled('event')) {
                try {
                    $editing = $this->graph->findEvent($account, $request->string('event')->value());
                } catch (Throwable $e) {
                    report($e);
                    $loadError = 'Ese evento ya no existe en Outlook.';
                }
            }
        }

        return Inertia::render('Events/Index', [
            'connected' => (bool) $account,
            'canWrite' => $canWrite,
            'timezone' => config('app.timezone'),
            'upcoming' => $upcoming,
            'editing' => $editing,
            'sharedWith' => $sharedWith,
            // Sin calendario dedicado, compartir afectaría la agenda personal.
            'dedicatedCalendar' => filled($account?->calendar_id),
            'loadError' => $loadError,
        ]);
    }

    public function store(StoreEventRequest $request): RedirectResponse
    {
        $account = $request->user()->microsoftAccount;

        try {
            $event = $this->graph->createEvent(
                $account,
                $this->withSharedGuests($account, $request->event(), $request->boolean('invite_shared', true)),
            );
        } catch (Throwable $e) {
            return $this->failed($e);
        }

        $this->announce($account, CalendarEventNotification::CREATED, $event);

        return back()->with('success', 'Evento creado. Te llegó un correo de confirmación.');
    }

    public function update(UpdateEventRequest $request): RedirectResponse
    {
        $account = $request->user()->microsoftAccount;
        $eventId = $request->string('event_id')->value();

        try {
            // Se lee antes del PATCH para poder contar qué cambió: después ya
            // no hay forma de saber cómo estaba.
            $previous = $this->graph->findEvent($account, $eventId);
            $event = $this->graph->updateEvent(
                $account,
                $eventId,
                $this->withSharedGuests($account, $request->event(), $request->boolean('invite_shared', true)),
            );
        } catch (Throwable $e) {
            return $this->failed($e);
        }

        $this->announce($account, CalendarEventNotification::UPDATED, $event, $previous);

        return back()->with('success', 'Evento actualizado. Te llegó un correo de confirmación.');
    }

    public function destroy(DeleteEventRequest $request): RedirectResponse
    {
        $account = $request->user()->microsoftAccount;
        $eventId = $request->string('event_id')->value();

        try {
            // Los datos se leen antes: una vez borrado Graph ya no los da.
            $event = $this->graph->findEvent($account, $eventId);
            $this->graph->deleteEvent($account, $eventId);
        } catch (Throwable $e) {
            return $this->failed($e);
        }

        $this->announce($account, CalendarEventNotification::DELETED, $event);

        return back()->with('success', 'Evento eliminado. Te llegó un correo de confirmación.');
    }

    /**
     * Manda el aviso al correo de la cuenta de Microsoft vinculada, que es la
     * que el usuario asocia con su agenda (puede no ser la de su cuenta local).
     *
     * @param  array<string, mixed>  $event
     * @param  array<string, mixed>|null  $previous  Cómo estaba antes de editarlo.
     */
    private function announce(
        MicrosoftAccount $account,
        string $action,
        array $event,
        ?array $previous = null,
    ): void {
        $recipients = [mb_strtolower($account->email), ...$this->sharedAudience($account, $event)];

        foreach (array_unique($recipients) as $email) {
            // Un fallo de correo no debe deshacer un cambio que Outlook ya
            // aceptó, ni impedir que los demás destinatarios reciban el suyo.
            try {
                Notification::route('mail', $email)
                    ->notify(new CalendarEventNotification($action, $event, $previous));
            } catch (Throwable $e) {
                report($e);
            }
        }

        // El calendario cachea por rango; sin esto el cambio no se ve.
        $account->bumpCalendarVersion();
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
    private function withSharedGuests(MicrosoftAccount $account, array $data, bool $invite): array
    {
        if (! $invite) {
            return $data;
        }

        try {
            $shared = $this->graph->calendarPermissions($account);
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
            ->reject(fn (string $email) => $email === mb_strtolower($account->email));

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
    private function sharedAudience(MicrosoftAccount $account, array $event): array
    {
        try {
            $shared = $this->graph->calendarPermissions($account);
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
            ->reject(fn (string $email) => $email === mb_strtolower($account->email))
            ->reject(fn (string $email) => in_array($email, $invited, true))
            ->unique()
            ->values()
            ->all();
    }

    private function failed(Throwable $e): RedirectResponse
    {
        report($e);

        $message = $e instanceof RequestException
            ? match ($e->response->status()) {
                401 => 'Tu sesión con Microsoft caducó. Vuelve a conectar la cuenta.',
                403 => 'Falta el permiso Calendars.ReadWrite. Reconecta tu cuenta para concederlo.',
                404 => 'El evento ya no existe en Outlook.',
                default => 'Microsoft Graph rechazó la operación ('.$e->response->status().').',
            }
            : 'No se pudo completar la operación en Outlook.';

        return back()->with('error', $message);
    }
}
