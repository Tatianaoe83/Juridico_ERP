<?php

namespace App\Services\Microsoft;

use App\Models\MicrosoftAccount;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Llamadas a Microsoft Graph.
 *
 * Dos modos, según quién sea el dueño del calendario (CalendarOwner):
 *  - delegado: token del usuario vinculado (/me), renovado con su refresh_token.
 *  - aplicación: token de la app (client credentials) sobre el buzón general
 *    (/users/{correo}). No hay sesión de Microsoft que mantener viva.
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
     * así que {raíz}/events/{id} lo encuentra esté en el calendario que esté.
     */
    private function calendarPath(CalendarOwner $calendar): string
    {
        return filled($calendar->calendarId())
            ? $calendar->graphRoot().'/calendars/'.rawurlencode($calendar->calendarId())
            : $calendar->graphRoot();
    }

    /**
     * El calendario como recurso, no como contenedor de eventos.
     * Ojo con el singular: sin id la ruta es {raíz}/calendar, no {raíz}.
     */
    private function calendarResource(CalendarOwner $calendar): string
    {
        return filled($calendar->calendarId())
            ? $calendar->graphRoot().'/calendars/'.rawurlencode($calendar->calendarId())
            : $calendar->graphRoot().'/calendar';
    }

    private function eventPath(CalendarOwner $calendar, string $eventId): string
    {
        return $calendar->graphRoot().'/events/'.rawurlencode($eventId);
    }

    /**
     * Deja lista la cuenta con su calendario dedicado y devuelve el id.
     * Solo en modo delegado: el buzón general ya es, entero, de la app.
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
    public function calendarPermissions(CalendarOwner $calendar): array
    {
        $response = $this->client($calendar)
            ->get(self::BASE.$this->calendarResource($calendar).'/calendarPermissions')
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
    public function shareCalendar(CalendarOwner $calendar, string $email, string $role): array
    {
        return $this->client($calendar)
            ->post(self::BASE.$this->calendarResource($calendar).'/calendarPermissions', [
                'emailAddress' => ['address' => $email, 'name' => $email],
                'role' => $role,
                'isRemovable' => true,
                'isInsideOrganization' => false,
            ])
            ->throw()
            ->json();
    }

    /** Revoca el acceso. Al que se lo quitas deja de ver el calendario. */
    public function unshareCalendar(CalendarOwner $calendar, string $permissionId): void
    {
        $this->client($calendar)
            ->delete(self::BASE.$this->calendarResource($calendar).'/calendarPermissions/'.rawurlencode($permissionId))
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
     * Eventos del calendario en el rango.
     *
     * @return array<int, array<string, mixed>>
     */
    public function calendarView(CalendarOwner $calendar, Carbon $from, Carbon $to): array
    {
        $response = $this->client($calendar)
            ->withHeaders(['Prefer' => 'outlook.timezone="'.config('app.timezone').'"'])
            ->get(self::BASE.$this->calendarPath($calendar).'/calendarView', [
                'startDateTime' => $from->toIso8601String(),
                'endDateTime' => $to->toIso8601String(),
                '$select' => 'id,subject,bodyPreview,start,end,isAllDay,location,organizer,attendees,responseStatus,webLink,showAs,isCancelled',
                '$orderby' => 'start/dateTime',
                '$top' => 200,
            ])
            ->throw();

        $calendar->markSynced();

        return collect($response->json('value', []))
            ->reject(fn (array $event) => $event['isCancelled'] ?? false)
            ->map(fn (array $event) => $this->mapEvent($event, $calendar->mailboxEmail()))
            ->values()
            ->all();
    }

    /**
     * Crea un evento en el calendario.
     * Queda en Outlook al instante: no hay copia local que mantener.
     *
     * @param  array{title: string, description: ?string, location: ?string, all_day: bool, start: Carbon, end: Carbon}  $data
     * @return array<string, mixed>
     */
    public function createEvent(CalendarOwner $calendar, array $data): array
    {
        $timezone = config('app.timezone');

        // En eventos de todo el día Graph exige medianoche y fin exclusivo:
        // un evento de un solo día va del día 1 al día 2.
        $start = $data['all_day'] ? $data['start']->copy()->startOfDay() : $data['start'];
        $end = $data['all_day'] ? $data['end']->copy()->startOfDay()->addDay() : $data['end'];

        $event = $this->client($calendar)
            ->post(self::BASE.$this->calendarPath($calendar).'/events', $this->payload($data, $start, $end, $timezone))
            ->throw()
            ->json();

        $calendar->markSynced();

        return $this->mapEvent($event, $calendar->mailboxEmail());
    }

    /**
     * Reemplaza un evento existente. Graph acepta PATCH parcial, pero se manda
     * el cuerpo completo: el formulario siempre trae todos los campos.
     *
     * @param  array{title: string, description: ?string, location: ?string, all_day: bool, start: Carbon, end: Carbon}  $data
     * @return array<string, mixed>
     */
    public function updateEvent(CalendarOwner $calendar, string $eventId, array $data): array
    {
        $timezone = config('app.timezone');
        $start = $data['all_day'] ? $data['start']->copy()->startOfDay() : $data['start'];
        $end = $data['all_day'] ? $data['end']->copy()->startOfDay()->addDay() : $data['end'];

        $event = $this->client($calendar)
            ->patch(
                self::BASE.$this->eventPath($calendar, $eventId),
                $this->payload($data, $start, $end, $timezone),
            )
            ->throw()
            ->json();

        $calendar->markSynced();

        return $this->mapEvent($event, $calendar->mailboxEmail());
    }

    /**
     * Un evento suelto. Se usa antes de borrar, para tener los datos con los
     * que redactar el aviso: después de borrarlo ya no se pueden pedir.
     *
     * @return array<string, mixed>
     */
    public function findEvent(CalendarOwner $calendar, string $eventId): array
    {
        $event = $this->client($calendar)
            ->withHeaders(['Prefer' => 'outlook.timezone="'.config('app.timezone').'"'])
            ->get(self::BASE.$this->eventPath($calendar, $eventId), [
                '$select' => 'id,subject,bodyPreview,start,end,isAllDay,location,organizer,attendees,responseStatus,webLink,showAs',
            ])
            ->throw()
            ->json();

        return $this->mapEvent($event, $calendar->mailboxEmail());
    }

    /** Borra el evento del calendario. Graph responde 204 sin cuerpo. */
    public function deleteEvent(CalendarOwner $calendar, string $eventId): void
    {
        $this->client($calendar)
            ->delete(self::BASE.$this->eventPath($calendar, $eventId))
            ->throw();

        $calendar->markSynced();
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
            // Siempre viaja, aunque venga vacío: así un PATCH limpia la
            // ubicación que hubiera quedado de antes.
            'location' => ['displayName' => (string) ($data['location'] ?? '')],
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
            // Recordatorio propio de Outlook: la alerta que salta en Outlook y
            // Teams. Quien no lo manda (los eventos normales) deja que cada
            // buzón use su valor por omisión.
            'isReminderOn' => array_key_exists('reminder_minutes', $data)
                ? $data['reminder_minutes'] !== null
                : null,
            'reminderMinutesBeforeStart' => $data['reminder_minutes'] ?? null,
        ], fn ($value) => $value !== null);
    }

    /**
     * Forma con la que viaja un evento al front y a las notificaciones.
     *
     * @param  array<string, mixed>  $event
     * @return array<string, mixed>
     */
    private function mapEvent(array $event, ?string $viewer = null): array
    {
        // En modo delegado cada buzón guarda su propia copia del evento y solo
        // la del organizador lleva las respuestas de los demás: en la copia de
        // quien fue invitado todos los asistentes salen como «none». Lo que sí
        // es fiable ahí es `responseStatus`, que es lo que contestó ese buzón,
        // así que con eso se corrige su propia fila.
        $mine = $event['responseStatus']['response'] ?? null;
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
                ->map(function (array $attendee) use ($viewer, $mine) {
                    $email = $attendee['emailAddress']['address'] ?? null;
                    // none · accepted · declined · tentativelyAccepted · organizer
                    $response = $attendee['status']['response'] ?? 'none';

                    $isViewer = $viewer !== null
                        && $email !== null
                        && strcasecmp($email, $viewer) === 0;

                    return [
                        'email' => $email,
                        'name' => $attendee['emailAddress']['name'] ?? null,
                        'response' => $isViewer && filled($mine) && $mine !== 'notResponded'
                            ? $mine
                            : $response,
                    ];
                })
                ->filter(fn (array $attendee) => filled($attendee['email']))
                ->values()
                ->all(),
        ];
    }

    /** Cliente autenticado con el token que corresponde al dueño. */
    private function client(CalendarOwner $calendar): PendingRequest
    {
        return Http::withToken($calendar instanceof MicrosoftAccount
            ? $this->freshToken($calendar)
            : $this->oauth->appToken());
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
