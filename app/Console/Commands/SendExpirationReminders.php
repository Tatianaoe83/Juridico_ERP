<?php

namespace App\Console\Commands;

use App\Models\License;
use App\Notifications\ExpirationReminderNotification;
use App\Support\ExpirationReminders;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Manda los recordatorios recurrentes de lo que vence. Corre una vez al día
 * (routes/console.php): cada registro con recordatorios prendidos recibe el
 * suyo solo los días que marca ExpirationReminders.
 */
class SendExpirationReminders extends Command
{
    protected $signature = 'recordatorios:enviar';

    protected $description = 'Envía por correo los recordatorios de vencimiento del día';

    public function handle(): int
    {
        $recipients = ExpirationReminders::recipients();

        if ($recipients === []) {
            $this->warn('No hay destinatarios: revisa MAIL_REMINDER_RECIPIENTS.');

            return self::SUCCESS;
        }

        $licenses = License::with('businessUnit')
            ->where('reminders_enabled', true)
            ->whereIn('valid_until', ExpirationReminders::dueDates())
            ->get();

        $sent = 0;

        foreach ($licenses as $license) {
            // Un correo que falla no detiene a los demás.
            try {
                Notification::route('mail', $recipients)->notify(new ExpirationReminderNotification(
                    kind: 'Licencia o permiso',
                    name: $license->name,
                    expiresOn: $license->valid_until,
                    details: [
                        'Unidad de negocio' => $license->businessUnit?->name,
                        'Autoridad' => $license->authority,
                    ],
                    url: route('licenses.index', ['search' => $license->name]),
                ));

                $sent++;
            } catch (Throwable $e) {
                report($e);
                $this->error("No se pudo enviar el de {$license->name}: {$e->getMessage()}");
            }
        }

        $this->info("Recordatorios enviados: {$sent}.");

        return self::SUCCESS;
    }
}
