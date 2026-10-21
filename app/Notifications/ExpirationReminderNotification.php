<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * Recordatorio recurrente de que algo vence: licencias hoy, y después
 * pólizas y fianzas. Cada módulo solo dice qué es, cuándo vence y su ficha.
 */
class ExpirationReminderNotification extends Notification
{
    use Queueable;

    /**
     * @param  string  $kind  Qué es, para el encabezado: «Licencia o permiso».
     * @param  array<string, ?string>  $details  Ficha: etiqueta => valor; los vacíos se omiten.
     */
    public function __construct(
        private readonly string $kind,
        private readonly string $name,
        private readonly Carbon $expiresOn,
        private readonly array $details = [],
        private readonly ?string $url = null,
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
        $remaining = $this->remaining();

        return (new MailMessage)
            ->subject("{$remaining}: {$this->name}")
            ->view('mail.expiration-reminder', [
                'kind' => $this->kind,
                'name' => $this->name,
                'remaining' => $remaining,
                'today' => today()->isSameDay($this->expiresOn),
                'details' => collect([
                    'Vence' => $this->expiresOn->copy()->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY'),
                    ...$this->details,
                ])
                    ->filter(fn (?string $value) => filled($value))
                    ->map(fn (string $value, string $label) => ['label' => $label, 'value' => $value])
                    ->values()
                    ->all(),
                'url' => $this->url,
            ]);
    }

    /** «Vence hoy», «Vence mañana», «Vence en 30 días». */
    private function remaining(): string
    {
        $days = (int) today()->diffInDays($this->expiresOn->copy()->startOfDay(), false);

        return match ($days) {
            0 => 'Vence hoy',
            1 => 'Vence mañana',
            default => "Vence en {$days} días",
        };
    }
}
