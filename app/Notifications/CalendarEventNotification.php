<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * Aviso por correo de lo que acaba de pasar con un evento del calendario.
 * Una sola clase para las tres acciones: cambia el verbo y, en la edición,
 * se añade la comparativa de lo que cambió.
 */
class CalendarEventNotification extends Notification
{
    use Queueable;

    public const CREATED = 'created';

    public const UPDATED = 'updated';

    public const DELETED = 'deleted';

    private const LABELS = [
        self::CREATED => 'creado',
        self::UPDATED => 'actualizado',
        self::DELETED => 'eliminado',
    ];

    /**
     * @param  array<string, mixed>  $event  Tal como lo devuelve MicrosoftGraph.
     * @param  array<string, mixed>|null  $previous  Estado anterior, solo al editar.
     */
    public function __construct(
        private readonly string $action,
        private readonly array $event,
        private readonly ?array $previous = null,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $label = self::LABELS[$this->action];

        return (new MailMessage)
            ->subject("Evento {$label}: ".$this->event['title'])
            ->view('mail.calendar-event', [
                'action' => $this->action,
                'label' => $label,
                'title' => $this->event['title'],
                'details' => $this->details(),
                'changes' => $this->changes(),
                'guests' => $this->guests(),
                // Un evento borrado ya no se puede abrir en Outlook.
                'url' => $this->action === self::DELETED ? null : ($this->event['url'] ?? null),
            ]);
    }

    /**
     * Ficha del evento. Se omiten los campos vacíos para no dejar renglones
     * sueltos con dos puntos y nada después.
     *
     * @return array<int, array{label: string, value: string}>
     */
    private function details(): array
    {
        $rows = [
            'Cuándo' => $this->when($this->event),
            'Ubicación' => $this->event['location'] ?? null,
            'Organiza' => $this->event['organizer'] ?? null,
            'Descripción' => $this->event['description'] ?? null,
        ];

        return collect($rows)
            ->filter(fn (?string $value) => filled($value))
            ->map(fn (string $value, string $label) => ['label' => $label, 'value' => $value])
            ->values()
            ->all();
    }

    /**
     * Qué cambió respecto al estado anterior. Vacío en alta y baja.
     *
     * @return array<int, array{label: string, before: string, after: string}>
     */
    private function changes(): array
    {
        if ($this->action !== self::UPDATED || $this->previous === null) {
            return [];
        }

        $comparisons = [
            'Fecha y hora' => [$this->when($this->previous), $this->when($this->event)],
            'Título' => [$this->previous['title'] ?? '', $this->event['title'] ?? ''],
            'Ubicación' => [$this->previous['location'] ?? '', $this->event['location'] ?? ''],
            'Descripción' => [$this->previous['description'] ?? '', $this->event['description'] ?? ''],
            'Invitados' => [$this->guestList($this->previous), $this->guestList($this->event)],
        ];

        return collect($comparisons)
            ->filter(fn (array $pair) => trim($pair[0]) !== trim($pair[1]))
            ->map(fn (array $pair, string $label) => [
                'label' => $label,
                // Un campo que antes estaba vacío no debe salir en blanco.
                'before' => filled($pair[0]) ? $pair[0] : '—',
                'after' => filled($pair[1]) ? $pair[1] : '—',
            ])
            ->values()
            ->all();
    }

    /**
     * Invitados con su respuesta, sin el organizador.
     *
     * @return array<int, array{name: string, response: string}>
     */
    private function guests(): array
    {
        $labels = [
            'accepted' => 'aceptó',
            'declined' => 'rechazó',
            'tentativelyAccepted' => 'quizá',
            'none' => 'sin responder',
        ];

        return collect($this->event['attendees'] ?? [])
            ->reject(fn (array $attendee) => ($attendee['response'] ?? '') === 'organizer')
            ->map(fn (array $attendee) => [
                'name' => $attendee['name'] ?: $attendee['email'],
                'response' => $labels[$attendee['response'] ?? 'none'] ?? 'sin responder',
            ])
            ->values()
            ->all();
    }

    /** Los invitados en una línea, para poder comparar antes y después. */
    private function guestList(array $event): string
    {
        return collect($event['attendees'] ?? [])
            ->reject(fn (array $attendee) => ($attendee['response'] ?? '') === 'organizer')
            ->pluck('email')
            ->filter()
            ->sort()
            ->implode(', ');
    }

    /** Fecha legible en español, con o sin hora según el tipo de evento. */
    private function when(array $event): string
    {
        if (blank($event['start'] ?? null)) {
            return '';
        }

        $start = Carbon::parse($event['start'])->locale('es');

        if ($event['all_day'] ?? false) {
            return $start->isoFormat('dddd D [de] MMMM [de] YYYY').' · todo el día';
        }

        $when = $start->isoFormat('dddd D [de] MMMM [de] YYYY, HH:mm');

        return blank($event['end'] ?? null)
            ? $when
            : $when.' – '.Carbon::parse($event['end'])->format('H:i');
    }
}
