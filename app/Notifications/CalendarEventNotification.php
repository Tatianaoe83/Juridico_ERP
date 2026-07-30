<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * Aviso por correo de lo que acaba de pasar con un evento del calendario.
 * Una sola clase para las tres acciones: cambia el verbo, no la estructura.
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
     */
    public function __construct(
        private readonly string $action,
        private readonly array $event,
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
                'when' => $this->when(),
                'location' => $this->event['location'] ?? null,
                // Un evento borrado ya no se puede abrir en Outlook.
                'url' => $this->action === self::DELETED ? null : ($this->event['url'] ?? null),
            ]);
    }

    /** Fecha legible en español, con o sin hora según el tipo de evento. */
    private function when(): string
    {
        if (blank($this->event['start'] ?? null)) {
            return '';
        }

        $start = Carbon::parse($this->event['start'])->locale('es');

        if ($this->event['all_day'] ?? false) {
            return $start->isoFormat('dddd D [de] MMMM [de] YYYY').' · todo el día';
        }

        $when = $start->isoFormat('dddd D [de] MMMM [de] YYYY, HH:mm');

        return blank($this->event['end'] ?? null)
            ? $when
            : $when.' – '.Carbon::parse($this->event['end'])->format('H:i');
    }
}
