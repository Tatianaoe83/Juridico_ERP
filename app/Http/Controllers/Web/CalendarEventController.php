<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Microsoft\MicrosoftGraph;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Throwable;

class CalendarEventController extends Controller
{
    public function __construct(private readonly MicrosoftGraph $graph) {}

    /** GET /calendario/eventos?start=&end= — eventos de Outlook del rango visible. */
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'start' => ['required', 'date'],
            'end' => ['required', 'date', 'after:start'],
        ]);

        $from = Carbon::parse($data['start'])->startOfDay();
        $to = Carbon::parse($data['end'])->endOfDay();
        $application = config('services.microsoft.mode') === 'application';

        $account = $request->user()->microsoftAccount;

        // Sin Calendars.Read la vinculación existe pero no sirve para leer eventos.
        if (! $application && ! $account?->canReadCalendar()) {
            return response()->json(['message' => 'Sin cuenta de Outlook conectada.'], 409);
        }

        $mailbox = $request->user()->email;
        $scope = $application ? "app:{$mailbox}" : "user:{$account->id}";
        $key = "graph:events:{$scope}:{$from->toDateString()}:{$to->toDateString()}";

        try {
            $events = Cache::remember($key, now()->addMinutes(5), fn () => $application
                ? $this->graph->calendarViewForMailbox($mailbox, $from, $to)
                : $this->graph->calendarView($account, $from, $to));
        } catch (RequestException $e) {
            report($e);

            return response()->json([
                'message' => $this->explain($e, $application, $mailbox),
                'reconnect' => ! $application,
            ], 502);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'No se pudieron leer los eventos de Outlook.',
                'reconnect' => ! $application,
            ], 502);
        }

        return response()->json(['data' => $events]);
    }

    /** Traduce los fallos típicos de Graph a algo accionable. */
    private function explain(RequestException $e, bool $application, string $mailbox): string
    {
        return match ($e->response->status()) {
            401 => $application
                ? 'El token de la aplicación fue rechazado. Revisa el secreto en el .env.'
                : 'Tu sesión con Microsoft caducó. Vuelve a conectar la cuenta.',
            403 => "La aplicación no tiene permiso para leer el buzón {$mailbox}. Falta el consentimiento de administrador o la política de acceso de Exchange lo bloquea.",
            404 => "No existe el buzón {$mailbox} en el tenant. El correo del usuario debe coincidir con su cuenta de Microsoft 365.",
            default => 'Microsoft Graph respondió con un error ('.$e->response->status().').',
        };
    }
}
