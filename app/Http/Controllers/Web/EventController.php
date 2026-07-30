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
        }

        return Inertia::render('Events/Index', [
            'connected' => (bool) $account,
            'canWrite' => $canWrite,
            'timezone' => config('app.timezone'),
            'upcoming' => $upcoming,
            'loadError' => $loadError,
        ]);
    }

    public function store(StoreEventRequest $request): RedirectResponse
    {
        $account = $request->user()->microsoftAccount;

        try {
            $event = $this->graph->createEvent($account, $request->event());
        } catch (Throwable $e) {
            return $this->failed($e);
        }

        $this->announce($account, CalendarEventNotification::CREATED, $event);

        return back()->with('success', 'Evento creado. Te llegó un correo de confirmación.');
    }

    public function update(UpdateEventRequest $request): RedirectResponse
    {
        $account = $request->user()->microsoftAccount;

        try {
            $event = $this->graph->updateEvent(
                $account,
                $request->string('event_id')->value(),
                $request->event(),
            );
        } catch (Throwable $e) {
            return $this->failed($e);
        }

        $this->announce($account, CalendarEventNotification::UPDATED, $event);

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
     */
    private function announce(MicrosoftAccount $account, string $action, array $event): void
    {
        // Un fallo de correo no debe deshacer un cambio que Outlook ya aceptó.
        try {
            Notification::route('mail', $account->email)
                ->notify(new CalendarEventNotification($action, $event));
        } catch (Throwable $e) {
            report($e);
        }

        // El calendario cachea por rango; sin esto el cambio no se ve.
        $account->bumpCalendarVersion();
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
