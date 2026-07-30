<?php

namespace App\Services\Microsoft;

use App\Models\MicrosoftAccount;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Llamadas a Microsoft Graph.
 *
 * Dos modos:
 *  - delegado: token del usuario vinculado (/me), renovado con su refresh_token.
 *  - aplicación: token de la app (client credentials) leyendo /users/{correo}.
 */
class MicrosoftGraph
{
    private const BASE = 'https://graph.microsoft.com/v1.0';

    public function __construct(private readonly MicrosoftOAuth $oauth) {}

    /** Perfil del usuario dueño del token recién emitido (modo delegado). */
    public function profile(string $accessToken): array
    {
        return Http::withToken($accessToken)
            ->get(self::BASE.'/me', ['$select' => 'id,displayName,mail,userPrincipalName'])
            ->throw()
            ->json();
    }

    /**
     * Modo delegado: eventos del usuario vinculado.
     *
     * @return array<int, array<string, mixed>>
     */
    public function calendarView(MicrosoftAccount $account, Carbon $from, Carbon $to): array
    {
        $events = $this->fetchEvents(
            Http::withToken($this->freshToken($account)),
            '/me/calendarView',
            $from,
            $to,
        );

        $account->forceFill(['synced_at' => now()])->save();

        return $events;
    }

    /**
     * Modo aplicación: eventos del buzón indicado, sin que el usuario haga nada.
     * El buzón debe existir en el tenant y estar permitido por la
     * ApplicationAccessPolicy de Exchange.
     *
     * @return array<int, array<string, mixed>>
     */
    public function calendarViewForMailbox(string $mailbox, Carbon $from, Carbon $to): array
    {
        return $this->fetchEvents(
            Http::withToken($this->oauth->appToken()),
            '/users/'.rawurlencode($mailbox).'/calendarView',
            $from,
            $to,
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchEvents(PendingRequest $client, string $path, Carbon $from, Carbon $to): array
    {
        $response = $client
            ->withHeaders(['Prefer' => 'outlook.timezone="'.config('app.timezone').'"'])
            ->get(self::BASE.$path, [
                'startDateTime' => $from->toIso8601String(),
                'endDateTime' => $to->toIso8601String(),
                '$select' => 'id,subject,start,end,isAllDay,location,organizer,webLink,showAs,isCancelled',
                '$orderby' => 'start/dateTime',
                '$top' => 200,
            ])
            ->throw();

        return collect($response->json('value', []))
            ->reject(fn (array $event) => $event['isCancelled'] ?? false)
            ->map(fn (array $event) => [
                'id' => $event['id'],
                'title' => $event['subject'] ?: '(Sin asunto)',
                'start' => $event['start']['dateTime'] ?? null,
                'end' => $event['end']['dateTime'] ?? null,
                'all_day' => $event['isAllDay'] ?? false,
                'location' => $event['location']['displayName'] ?? null,
                'organizer' => $event['organizer']['emailAddress']['name'] ?? null,
                'status' => $event['showAs'] ?? null,
                'url' => $event['webLink'] ?? null,
            ])
            ->values()
            ->all();
    }

    /** Devuelve un access_token de usuario vigente, renovándolo si hace falta. */
    private function freshToken(MicrosoftAccount $account): string
    {
        if (! $account->tokenExpired()) {
            return $account->access_token;
        }

        $tokens = $this->oauth->refresh(
            $account->refresh_token,
            $account->scopes ?: MicrosoftOAuth::LOGIN_SCOPES,
        );

        $account->forceFill([
            'access_token' => $tokens['access_token'],
            // Microsoft rota el refresh_token; si viene uno nuevo, reemplaza.
            'refresh_token' => $tokens['refresh_token'] ?? $account->refresh_token,
            'expires_at' => now()->addSeconds((int) ($tokens['expires_in'] ?? 3600)),
        ])->save();

        return $account->access_token;
    }
}
