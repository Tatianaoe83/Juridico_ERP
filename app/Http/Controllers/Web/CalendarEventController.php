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
    /**
     * Ventana del caché. Corta a propósito: los cambios hechos desde Outlook no
     * avisan a la app, así que este es el retraso máximo con el que se ven.
     */
    private const CACHE_MINUTES = 2;

    public function __construct(private readonly MicrosoftGraph $graph) {}

    /** GET /calendario/eventos?start=&end= — eventos de Outlook del rango visible. */
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'start' => ['required', 'date'],
            'end' => ['required', 'date', 'after:start'],
            // El botón Actualizar lo manda: los cambios hechos desde Outlook no
            // pasan por la app, así que nada invalida el caché por su cuenta.
            'fresh' => ['sometimes', 'boolean'],
        ]);

        $from = Carbon::parse($data['start'])->startOfDay();
        $to = Carbon::parse($data['end'])->endOfDay();
        $application = config('services.microsoft.mode') === 'application';

        $calendar = $request->user()->calendarOwner();

        // Sin Calendars.Read la vinculación existe pero no sirve para leer eventos.
        if (! $calendar?->canReadCalendar()) {
            return response()->json([
                'message' => $application
                    ? 'Falta configurar el buzón general (MS_MAILBOX).'
                    : 'Sin cuenta de Outlook conectada.',
            ], 409);
        }

        $mailbox = $calendar->mailboxEmail();
        // La versión hace que un evento recién creado invalide lo cacheado.
        $scope = "{$mailbox}:v{$calendar->calendarVersion()}";
        $key = "graph:events:{$scope}:{$from->toDateString()}:{$to->toDateString()}";

        if ($request->boolean('fresh')) {
            Cache::forget($key);
        }

        try {
            $events = Cache::remember(
                $key,
                now()->addMinutes(self::CACHE_MINUTES),
                fn () => $this->graph->calendarView($calendar, $from, $to),
            );
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
            404 => "No existe el buzón {$mailbox} en el tenant. Revisa MS_MAILBOX.",
            default => 'Microsoft Graph respondió con un error ('.$e->response->status().').',
        };
    }
}
