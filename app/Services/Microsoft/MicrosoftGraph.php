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
     * Crea un evento en el calendario principal del usuario vinculado.
     * Queda en Outlook al instante: no hay copia local que mantener.
     *
     * @param  array{title: string, description: ?string, location: ?string, all_day: bool, start: Carbon, end: Carbon}  $data
     * @return array<string, mixed>
     */
    public function createEvent(MicrosoftAccount $account, array $data): array
    {
        $timezone = config('app.timezone');

        // En eventos de todo el día Graph exige medianoche y fin exclusivo:
        // un evento de un solo día va del día 1 al día 2.
        $start = $data['all_day'] ? $data['start']->copy()->startOfDay() : $data['start'];
        $end = $data['all_day'] ? $data['end']->copy()->startOfDay()->addDay() : $data['end'];

        $event = Http::withToken($this->freshToken($account))
            ->post(self::BASE.'/me/events', $this->payload($data, $start, $end, $timezone))
            ->throw()
            ->json();

        $account->forceFill(['synced_at' => now()])->save();

        return $this->mapEvent($event);
    }

    /**
     * Reemplaza un evento existente. Graph acepta PATCH parcial, pero se manda
     * el cuerpo completo: el formulario siempre trae todos los campos.
     *
     * @param  array{title: string, description: ?string, location: ?string, all_day: bool, start: Carbon, end: Carbon}  $data
     * @return array<string, mixed>
     */
    public function updateEvent(MicrosoftAccount $account, string $eventId, array $data): array
    {
        $timezone = config('app.timezone');
        $start = $data['all_day'] ? $data['start']->copy()->startOfDay() : $data['start'];
        $end = $data['all_day'] ? $data['end']->copy()->startOfDay()->addDay() : $data['end'];

        $event = Http::withToken($this->freshToken($account))
            ->patch(
                self::BASE.'/me/events/'.rawurlencode($eventId),
                $this->payload($data, $start, $end, $timezone),
            )
            ->throw()
            ->json();

        $account->forceFill(['synced_at' => now()])->save();

        return $this->mapEvent($event);
    }

    /**
     * Un evento suelto. Se usa antes de borrar, para tener los datos con los
     * que redactar el aviso: después de borrarlo ya no se pueden pedir.
     *
     * @return array<string, mixed>
     */
    public function findEvent(MicrosoftAccount $account, string $eventId): array
    {
        $event = Http::withToken($this->freshToken($account))
            ->withHeaders(['Prefer' => 'outlook.timezone="'.config('app.timezone').'"'])
            ->get(self::BASE.'/me/events/'.rawurlencode($eventId), [
                '$select' => 'id,subject,bodyPreview,start,end,isAllDay,location,organizer,webLink,showAs',
            ])
            ->throw()
            ->json();

        return $this->mapEvent($event);
    }

    /** Borra el evento del calendario. Graph responde 204 sin cuerpo. */
    public function deleteEvent(MicrosoftAccount $account, string $eventId): void
    {
        Http::withToken($this->freshToken($account))
            ->delete(self::BASE.'/me/events/'.rawurlencode($eventId))
            ->throw();

        $account->forceFill(['synced_at' => now()])->save();
    }

    /**
     * Cuerpo que entiende Graph, común al alta y a la edición.
     *
     * @param  array{title: string, description: ?string, location: ?string, all_day: bool}  $data
     * @return array<string, mixed>
     */
    private function payload(array $data, Carbon $start, Carbon $end, string $timezone): array
    {
        $format = fn (Carbon $moment) => $moment->format('Y-m-d\TH:i:s');

        return array_filter([
            'subject' => $data['title'],
            'isAllDay' => $data['all_day'],
            'start' => ['dateTime' => $format($start), 'timeZone' => $timezone],
            'end' => ['dateTime' => $format($end), 'timeZone' => $timezone],
            'body' => filled($data['description'])
                ? ['contentType' => 'text', 'content' => $data['description']]
                : null,
            'location' => filled($data['location'])
                ? ['displayName' => $data['location']]
                : null,
        ], fn ($value) => $value !== null);
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
                '$select' => 'id,subject,bodyPreview,start,end,isAllDay,location,organizer,webLink,showAs,isCancelled',
                '$orderby' => 'start/dateTime',
                '$top' => 200,
            ])
            ->throw();

        return collect($response->json('value', []))
            ->reject(fn (array $event) => $event['isCancelled'] ?? false)
            ->map(fn (array $event) => $this->mapEvent($event))
            ->values()
            ->all();
    }

    /**
     * Forma con la que viaja un evento al front y a las notificaciones.
     *
     * @param  array<string, mixed>  $event
     * @return array<string, mixed>
     */
    private function mapEvent(array $event): array
    {
        return [
            'id' => $event['id'],
            'title' => ($event['subject'] ?? '') ?: '(Sin asunto)',
            // Texto plano del cuerpo: basta para reponerlo al editar.
            'description' => $event['bodyPreview'] ?? null,
            'start' => $event['start']['dateTime'] ?? null,
            'end' => $event['end']['dateTime'] ?? null,
            'all_day' => $event['isAllDay'] ?? false,
            'location' => $event['location']['displayName'] ?? null,
            'organizer' => $event['organizer']['emailAddress']['name'] ?? null,
            'status' => $event['showAs'] ?? null,
            'url' => $event['webLink'] ?? null,
        ];
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
