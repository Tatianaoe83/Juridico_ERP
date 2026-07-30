<?php

namespace App\Services\Microsoft;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Flujo OAuth 2.0 (authorization code) contra Entra ID.
 * Sin SDK: son dos endpoints y un POST.
 */
class MicrosoftOAuth
{
    /**
     * Identidad. Es lo que pide el inicio de sesión con Microsoft.
     * offline_access es lo que da refresh_token.
     */
    public const LOGIN_SCOPES = 'openid profile email offline_access User.Read';

    /**
     * Identidad + lectura del calendario.
     * Calendars.Read exige consentimiento de administrador en el tenant,
     * por eso va aparte: si se pidiera en el login, nadie podría entrar.
     */
    public const CALENDAR_SCOPES = self::LOGIN_SCOPES.' Calendars.Read';

    public function configured(): bool
    {
        return filled(config('services.microsoft.client_id'))
            && filled(config('services.microsoft.client_secret'))
            && filled(config('services.microsoft.tenant'));
    }

    /** URL a la que se manda al usuario para que autorice. */
    public function authorizationUrl(string $state, string $scopes = self::CALENDAR_SCOPES): string
    {
        return $this->endpoint('authorize').'?'.http_build_query([
            'client_id' => config('services.microsoft.client_id'),
            'response_type' => 'code',
            'redirect_uri' => config('services.microsoft.redirect'),
            'response_mode' => 'query',
            'scope' => $scopes,
            'state' => $state,
            'prompt' => 'select_account',
        ]);
    }

    /**
     * Canjea el código de autorización por tokens.
     *
     * @return array{access_token: string, refresh_token: ?string, expires_in: int, scope: ?string}
     */
    public function exchangeCode(string $code): array
    {
        return $this->token([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => config('services.microsoft.redirect'),
        ]);
    }

    /**
     * Token de aplicación (client credentials): la app se autentica sola,
     * sin usuario. Requiere permisos de aplicación con consentimiento de admin.
     * Vive ~1h, se cachea 55 min.
     */
    public function appToken(): string
    {
        return Cache::remember('microsoft:app_token', now()->addMinutes(55), function () {
            $tokens = $this->token([
                'grant_type' => 'client_credentials',
                'scope' => 'https://graph.microsoft.com/.default',
            ]);

            return $tokens['access_token'];
        });
    }

    /** Renueva el access_token con el refresh_token guardado. */
    public function refresh(string $refreshToken, string $scopes = self::LOGIN_SCOPES): array
    {
        return $this->token([
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'scope' => $scopes,
        ]);
    }

    /**
     * @param  array<string, string>  $payload
     * @return array<string, mixed>
     */
    private function token(array $payload): array
    {
        $response = Http::asForm()->post($this->endpoint('token'), [
            'client_id' => config('services.microsoft.client_id'),
            'client_secret' => config('services.microsoft.client_secret'),
            ...$payload,
        ]);

        if ($response->failed()) {
            throw new RuntimeException(
                'Entra ID rechazó la petición de token: '
                .($response->json('error_description') ?? $response->body())
            );
        }

        return $response->json();
    }

    private function endpoint(string $action): string
    {
        $tenant = config('services.microsoft.tenant');

        return "https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/{$action}";
    }
}
