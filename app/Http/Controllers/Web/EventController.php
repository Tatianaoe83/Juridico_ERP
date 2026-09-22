<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Calendar\DeleteEventRequest;
use App\Http\Requests\Calendar\StoreEventRequest;
use App\Http\Requests\Calendar\UpdateEventRequest;
use App\Services\CalendarEvents;
use App\Services\Microsoft\MicrosoftGraph;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Alta, edición y baja de eventos en Outlook: en el buzón general (modo
 * aplicación) o en el calendario del propio usuario (delegado).
 * No hay copia local: Graph es la única fuente. Cada acción avisa por correo.
 * La lógica vive en CalendarEvents para poder usarla desde otras partes.
 */
class EventController extends Controller
{
    /** Ventana de la lista de próximos eventos, en días. */
    private const UPCOMING_DAYS = 30;

    public function __construct(
        private readonly MicrosoftGraph $graph,
        private readonly CalendarEvents $events,
    ) {}

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
        try {
            $this->events->create($request->user(), $request->event(), $request->boolean('invite_shared', true));
        } catch (Throwable $e) {
            return $this->failed($e);
        }

        return back()->with('success', 'Evento creado. Te llegó un correo de confirmación.');
    }

    public function update(UpdateEventRequest $request): RedirectResponse
    {
        try {
            $this->events->update(
                $request->user(),
                $request->string('event_id')->value(),
                $request->event(),
                $request->boolean('invite_shared', true),
            );
        } catch (Throwable $e) {
            return $this->failed($e);
        }

        return back()->with('success', 'Evento actualizado. Te llegó un correo de confirmación.');
    }

    public function destroy(DeleteEventRequest $request): RedirectResponse
    {
        try {
            $this->events->delete($request->user(), $request->string('event_id')->value());
        } catch (Throwable $e) {
            return $this->failed($e);
        }

        return back()->with('success', 'Evento eliminado. Te llegó un correo de confirmación.');
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
