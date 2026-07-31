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

    /**
     * Base sobre la que se leen y crean eventos. Sin calendario dedicado va al
     * principal del buzón; con uno, al que le pertenece a esa cuenta.
     *
     * Editar y borrar no la usan: el id de un evento es único en todo el buzón,
     * así que /me/events/{id} lo encuentra esté en el calendario que esté.
     */
    private function calendarPath(MicrosoftAccount $account): string
    {
        return filled($account->calendar_id)
            ? '/me/calendars/'.rawurlencode($account->calendar_id)
            : '/me';
    }

    /**
     * El calendario como recurso, no como contenedor de eventos.
     * Ojo con el singular: sin id la ruta es /me/calendar, no /me.
     */
    private function calendarResource(MicrosoftAccount $account): string
    {
        return filled($account->calendar_id)
            ? '/me/calendars/'.rawurlencode($account->calendar_id)
            : '/me/calendar';
    }

    /**
     * Deja lista la cuenta con su calendario dedicado y devuelve el id.
     *
     * Idempotente a propósito: si el id guardado ya no existe en Outlook —
     * porque lo borraron desde ahí— crea uno nuevo en vez de dejar la cuenta
     * apuntando a un calendario fantasma.
     */
    public function ensureCalendar(MicrosoftAccount $account, ?string $name = null): string
    {
        if (filled($account->calendar_id) && $this->calendarExists($account)) {
            return $account->calendar_id;
        }

        $name = $name ?: config('services.microsoft.calendar_name');
        $token = $this->freshToken($account);

        // Outlook rechaza dos calendarios con el mismo nombre. Si ya hay uno
        // —por ejemplo al desvincular y volver a vincular, que borra el id
        // guardado— se adopta en vez de intentar crearlo y chocar con un 409.
        $existing = collect(
            Http::withToken($token)
                ->get(self::BASE.'/me/calendars', ['$select' => 'id,name'])
                ->throw()
                ->json('value', []),
        )->firstWhere('name', $name);

        $calendarId = $existing['id'] ?? Http::withToken($token)
            ->post(self::BASE.'/me/calendars', ['name' => $name])
            ->throw()
            ->json('id');

        $account->forceFill(['calendar_id' => $calendarId])->save();

        return $calendarId;
    }

    private function calendarExists(MicrosoftAccount $account): bool
    {
        return Http::withToken($this->freshToken($account))
            ->get(self::BASE.'/me/calendars/'.rawurlencode($account->calendar_id), ['$select' => 'id'])
            ->successful();
    }

    /**
     * Con quién está compartido el calendario. Esta es la lista de vinculados
     * que un .ics publicado nunca puede dar: Outlook la mantiene.
     *
     * @return array<int, array<string, mixed>>
     */
    public function calendarPermissions(MicrosoftAccount $account): array
    {
        $response = Http::withToken($this->freshToken($account))
            ->get(self::BASE.$this->calendarResource($account).'/calendarPermissions')
            ->throw();

        return collect($response->json('value', []))
            ->map(fn (array $permission) => [
                'id' => $permission['id'],
                'email' => $permission['emailAddress']['address'] ?? null,
                'name' => $permission['emailAddress']['name'] ?? null,
                'role' => $permission['role'] ?? 'none',
                // El propietario aparece en la lista y no se puede quitar.
                'removable' => $permission['isRemovable'] ?? false,
            ])
            ->filter(fn (array $permission) => filled($permission['email']))
            ->values()
            ->all();
    }

    /**
     * Comparte el calendario con alguien. Outlook le manda la invitación para
     * agregarlo, y a partir de ahí ve los cambios sin que la app haga nada.
     *
     * @return array<string, mixed>
     */
    public function shareCalendar(MicrosoftAccount $account, string $email, string $role): array
    {
        return Http::withToken($this->freshToken($account))
            ->post(self::BASE.$this->calendarResource($account).'/calendarPermissions', [
                'emailAddress' => ['address' => $email, 'name' => $email],
                'role' => $role,
                'isRemovable' => true,
                'isInsideOrganization' => false,
            ])
            ->throw()
            ->json();
    }

    /** Revoca el acceso. Al que se lo quitas deja de ver el calendario. */
    public function unshareCalendar(MicrosoftAccount $account, string $permissionId): void
    {
        Http::withToken($this->freshToken($account))
            ->delete(self::BASE.$this->calendarResource($account).'/calendarPermissions/'.rawurlencode($permissionId))
            ->throw();
    }

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
            $this->calendarPath($account).'/calendarView',
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
            ->post(self::BASE.$this->calendarPath($account).'/events', $this->payload($data, $start, $end, $timezone))
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
                '$select' => 'id,subject,bodyPreview,start,end,isAllDay,location,organizer,attendees,webLink,showAs',
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

        $attendees = $data['attendees'] ?? [];

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
            // Con asistentes, Outlook manda la invitación, propaga los cambios
            // y avisa la cancelación. Nada de eso lo hace la app.
            'attendees' => collect($attendees)
                ->map(fn (string $email) => [
                    'emailAddress' => ['address' => $email],
                    'type' => 'required',
                ])
                ->all(),
            // Sin esto la invitación llega sin botones de Aceptar/Rechazar.
            'responseRequested' => $attendees !== [],
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
                '$select' => 'id,subject,bodyPreview,start,end,isAllDay,location,organizer,attendees,webLink,showAs,isCancelled',
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
            // Quiénes están vinculados a este evento y qué contestaron.
            // Graph mantiene la lista: no hay tabla local que sincronizar.
            'attendees' => collect($event['attendees'] ?? [])
                ->map(fn (array $attendee) => [
                    'email' => $attendee['emailAddress']['address'] ?? null,
                    'name' => $attendee['emailAddress']['name'] ?? null,
                    // none · accepted · declined · tentativelyAccepted · organizer
                    'response' => $attendee['status']['response'] ?? 'none',
                ])
                ->filter(fn (array $attendee) => filled($attendee['email']))
                ->values()
                ->all(),
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
