<?php

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Controller;
use App\Models\MicrosoftAccount;
use App\Services\Microsoft\MicrosoftGraph;
use App\Services\Microsoft\MicrosoftIdentity;
use App\Services\Microsoft\MicrosoftOAuth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Throwable;

class MicrosoftController extends Controller
{
    public function __construct(
        private readonly MicrosoftOAuth $oauth,
        private readonly MicrosoftGraph $graph,
        private readonly MicrosoftIdentity $identity,
    ) {}

    /**
     * Inicio de sesión con Microsoft. Pide solo identidad, que es lo que el
     * tenant deja consentir al propio usuario.
     */
    public function login(Request $request): RedirectResponse
    {
        return $this->start($request, MicrosoftOAuth::LOGIN_SCOPES);
    }

    /**
     * Consentimiento del calendario para quien ya entró. Añade Calendars.Read
     * sobre lo ya concedido (consentimiento incremental).
     */
    public function redirect(Request $request): RedirectResponse
    {
        return $this->start($request, MicrosoftOAuth::CALENDAR_SCOPES);
    }

    /** Manda al usuario a la pantalla de consentimiento de Microsoft. */
    private function start(Request $request, string $scopes): RedirectResponse
    {
        if (! $this->oauth->configured()) {
            return back()->with('error', 'Faltan las credenciales de Microsoft en el archivo .env.');
        }

        $state = Str::random(40);

        $request->session()->put('microsoft_state', $state);
        $request->session()->put('microsoft_scopes', $scopes);

        return redirect()->away($this->oauth->authorizationUrl($state, $scopes));
    }

    /** Vuelta desde Microsoft: canjea el código y guarda la vinculación. */
    public function callback(Request $request): RedirectResponse
    {
        $scopes = $request->session()->pull('microsoft_scopes', MicrosoftOAuth::LOGIN_SCOPES);
        $state = $request->session()->pull('microsoft_state');

        if ($request->filled('error')) {
            return $this->fail('Microsoft no autorizó la conexión: '.$request->string('error_description'));
        }

        if (! $request->filled('code') || ! $state || ! hash_equals($state, $request->string('state')->value())) {
            return $this->fail('La respuesta de Microsoft no coincide con la solicitud. Intenta de nuevo.');
        }

        try {
            $tokens = $this->oauth->exchangeCode($request->string('code')->value());
            $profile = $this->graph->profile($tokens['access_token']);
            $email = $this->identity->email($profile);
        } catch (Throwable $e) {
            report($e);

            return $this->fail('No se pudo completar la conexión con Microsoft.');
        }

        if (! $this->identity->domainAllowed($email)) {
            return $this->fail("La cuenta {$email} no pertenece a la organización.");
        }

        // Si ya hay sesión, la vinculación es para ese usuario. Si no, el SSO
        // resuelve a quién pertenece el perfil (y lo da de alta si hace falta).
        $user = Auth::user() ?? $this->identity->resolveUser($profile, $email);

        $account = MicrosoftAccount::updateOrCreate(
            ['user_id' => $user->id],
            [
                'microsoft_id' => $profile['id'],
                'tenant_id' => config('services.microsoft.tenant'),
                'email' => $email,
                'display_name' => $profile['displayName'] ?? null,
                'access_token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'] ?? null,
                'expires_at' => now()->addSeconds((int) ($tokens['expires_in'] ?? 3600)),
                'scopes' => $scopes,
            ],
        );

        // Vincular la cuenta deja listo su calendario dedicado, que es el que
        // se comparte. Solo es posible con permiso de escritura: quien entra
        // por SSO todavía no lo concedió y lo obtendrá al conectar su agenda.
        if ($account->canWriteCalendar()) {
            try {
                $this->graph->ensureCalendar($account);
            } catch (Throwable $e) {
                // Que falle no debe impedir el acceso: la app funciona sobre el
                // calendario principal y se reintenta en la siguiente conexión.
                report($e);
            }
        }

        if (! Auth::check()) {
            Auth::login($user, remember: true);
            $request->session()->regenerate();

            return redirect()->intended(route('calendar.index'));
        }

        return redirect()->route('calendar.index')->with('success', 'Cuenta de Outlook conectada.');
    }

    /** Desvincula la cuenta y borra los tokens guardados. */
    public function destroy(Request $request): RedirectResponse
    {
        $request->user()->microsoftAccount?->delete();

        return back()->with('success', 'Cuenta de Outlook desvinculada.');
    }

    /** Un invitado no puede volver al calendario; lo regresa al login. */
    private function fail(string $message): RedirectResponse
    {
        $route = Auth::check() ? 'calendar.index' : 'login';

        return redirect()->route($route)->with('error', $message);
    }
}
