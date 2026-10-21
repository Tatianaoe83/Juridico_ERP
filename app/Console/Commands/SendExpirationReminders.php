<?php

namespace App\Console\Commands;

use App\Models\Bond;
use App\Models\ConstructionPolicy;
use App\Models\License;
use App\Models\UnitPolicy;
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

        $sent = 0;

        foreach ([...$this->licenses(), ...$this->unitPolicies(), ...$this->constructionPolicies(), ...$this->bonds()] as $reminder) {
            // Un correo que falla no detiene a los demás.
            try {
                Notification::route('mail', $recipients)->notify($reminder);

                $sent++;
            } catch (Throwable $e) {
                report($e);
                $this->error("No se pudo enviar el de {$reminder->name}: {$e->getMessage()}");
            }
        }

        $this->info("Recordatorios enviados: {$sent}.");

        return self::SUCCESS;
    }

    /** @return list<ExpirationReminderNotification> */
    private function licenses(): array
    {
        return License::with('businessUnit')
            ->where('reminders_enabled', true)
            ->whereIn('valid_until', ExpirationReminders::dueDates())
            ->get()
            ->map(fn (License $license) => new ExpirationReminderNotification(
                kind: 'Licencia o permiso',
                name: $license->name,
                expiresOn: $license->valid_until,
                details: [
                    'Unidad de negocio' => $license->businessUnit?->name,
                    'Autoridad' => $license->authority,
                ],
                url: route('licenses.index', ['search' => $license->name]),
            ))
            ->all();
    }

    /**
     * Las de flotillas: automóvil y maquinaria, empresariales y preferentes.
     * Vencen al cierre de su vigencia; las canceladas ya no avisan.
     *
     * @return list<ExpirationReminderNotification>
     */
    private function unitPolicies(): array
    {
        return UnitPolicy::with(['unit.businessUnit', 'businessUnit', 'insurer', 'supplier', 'machines'])
            ->where('reminders_enabled', true)
            ->whereNull('cancelled_on')
            ->whereIn('second_payment_ends_on', ExpirationReminders::dueDates())
            ->get()
            ->map(function (UnitPolicy $policy) {
                $machinery = $policy->isMachinery();
                $kind = $machinery ? 'Póliza de maquinaria' : 'Póliza de automóvil';
                $machines = $policy->machines->count();

                return new ExpirationReminderNotification(
                    kind: $policy->isFromSupplier() ? "{$kind} con beneficiario preferente" : $kind,
                    name: "Póliza {$policy->policy}",
                    expiresOn: $policy->second_payment_ends_on,
                    details: [
                        'Unidad de negocio' => $policy->businessUnitName(),
                        'Aseguradora' => $policy->insurer?->name,
                        'Beneficiario preferente' => $policy->supplier?->name,
                        'Cubre' => $machinery
                            ? $machines.' '.($machines === 1 ? 'máquina' : 'máquinas')
                            : trim("{$policy->unit?->brand} {$policy->unit?->model} {$policy->unit?->plate}"),
                    ],
                    url: $machinery ? route('fleets.machinery.show', $policy) : route('fleets.show', $policy->unit_id),
                );
            })
            ->all();
    }

    /**
     * Las de obra: su fin de vigencia ya es el del último endoso.
     *
     * @return list<ExpirationReminderNotification>
     */
    private function constructionPolicies(): array
    {
        return ConstructionPolicy::with(['businessUnit', 'insurer', 'project'])
            ->where('reminders_enabled', true)
            ->whereIn('valid_until', ExpirationReminders::dueDates())
            ->get()
            ->map(fn (ConstructionPolicy $policy) => new ExpirationReminderNotification(
                kind: 'Póliza de obra',
                name: "Póliza {$policy->policy}",
                expiresOn: $policy->valid_until,
                details: [
                    'Unidad de negocio' => $policy->businessUnit?->name,
                    'Aseguradora' => $policy->insurer?->name,
                    'Obra' => $policy->project?->name,
                ],
                url: route('policies.show', $policy),
            ))
            ->all();
    }

    /**
     * Las fianzas; las canceladas ya no avisan.
     *
     * @return list<ExpirationReminderNotification>
     */
    private function bonds(): array
    {
        return Bond::with(['businessUnit', 'bondingCompany', 'beneficiary'])
            ->where('reminders_enabled', true)
            ->whereNull('cancelled_on')
            ->whereIn('valid_until', ExpirationReminders::dueDates())
            ->get()
            ->map(fn (Bond $bond) => new ExpirationReminderNotification(
                kind: 'Fianza',
                name: "Fianza {$bond->bond}",
                expiresOn: $bond->valid_until,
                details: [
                    'Unidad de negocio' => $bond->businessUnit?->name,
                    'Afianzadora' => $bond->bondingCompany?->name,
                    'Beneficiario' => $bond->beneficiary?->name,
                    'Asunto' => $bond->related,
                ],
                url: route('policies.bonds.show', $bond),
            ))
            ->all();
    }
}
