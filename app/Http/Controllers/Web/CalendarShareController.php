<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CalendarShare;
use App\Services\Microsoft\MicrosoftGraph;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Con quién se comparte el calendario de la app.
 *
 * La lista la mantiene Outlook, no una tabla local: por eso aquí solo hay alta
 * y baja, y la lectura vive en EventController::index.
 */
class CalendarShareController extends Controller
{
    public function __construct(private readonly MicrosoftGraph $graph) {}

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'role' => ['required', Rule::in(CalendarShare::ROLES)],
        ]);

        $account = $request->user()->microsoftAccount;

        if (! $account?->canWriteCalendar()) {
            return back()->with('error', 'Reconecta tu cuenta para poder compartir el calendario.');
        }

        $email = mb_strtolower($data['email']);

        try {
            $permission = $this->graph->shareCalendar($account, $email, $data['role']);
        } catch (Throwable $e) {
            return $this->failed($e, $data['email']);
        }

        /*
         * Copia local del acceso. Es lo que permite que al invitado le aparezca
         * el calendario dentro de la app: sin esto tendría que vincular su
         * propia cuenta de Microsoft y acabaría con un calendario aparte.
         */
        CalendarShare::updateOrCreate(
            ['microsoft_account_id' => $account->id, 'email' => $email],
            ['role' => $data['role'], 'permission_id' => $permission['id'] ?? null],
        );

        return back()->with('success', "Calendario compartido con {$data['email']}.");
    }

    public function destroy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'permission_id' => ['required', 'string'],
        ]);

        $account = $request->user()->microsoftAccount;

        if (! $account?->canWriteCalendar()) {
            return back()->with('error', 'Reconecta tu cuenta para poder dejar de compartir.');
        }

        try {
            $this->graph->unshareCalendar($account, $data['permission_id']);
        } catch (Throwable $e) {
            return $this->failed($e);
        }

        CalendarShare::where('microsoft_account_id', $account->id)
            ->where('permission_id', $data['permission_id'])
            ->delete();

        return back()->with('success', 'Acceso revocado.');
    }

    private function failed(Throwable $e, ?string $email = null): RedirectResponse
    {
        report($e);

        $message = $e instanceof RequestException
            ? match ($e->response->status()) {
                // Los niveles intermedios son de Exchange Online: una cuenta
                // personal de Outlook solo admite ver detalles y editar.
                400 => 'Esa cuenta de Microsoft no admite ese nivel de acceso. '
                    .'Prueba con "Ver los detalles" o "Editar eventos".',
                401 => 'Tu sesión con Microsoft caducó. Vuelve a conectar la cuenta.',
                403 => 'Este calendario no se puede compartir. Outlook no lo permite en calendarios del sistema.',
                404 => 'El acceso ya no existe.',
                409 => "El calendario ya está compartido con {$email}.",
                default => 'Microsoft Graph rechazó la operación ('.$e->response->status().').',
            }
            : 'No se pudo completar la operación en Outlook.';

        return back()->with('error', $message);
    }
}
