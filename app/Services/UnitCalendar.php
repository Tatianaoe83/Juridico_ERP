<?php

namespace App\Services;

use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Agenda en Outlook el vencimiento de cada pago semestral de una unidad.
 *
 * Aquí solo se arma el evento; cómo se inserta lo resuelve CalendarEvents.
 *
 * Nada de esto puede tumbar el alta: si Graph falla o la cuenta no está
 * vinculada, la unidad se guarda igual y el evento sencillamente no se crea.
 * Por eso los métodos devuelven bool en vez de lanzar.
 */
class UnitCalendar
{
    /**
     * Los dos semestres, con la columna de la que sale su fecha y dónde se
     * guarda el id del evento. La fecha es la de cierre: el pago vence cuando
     * el periodo termina, nunca cuando empieza.
     *
     * @var array<int, array{label: string, date: string, event: string}>
     */
    private const PAYMENTS = [
        ['label' => 'Primer pago', 'date' => 'first_payment_ends_on', 'event' => 'first_payment_event_id'],
        ['label' => 'Segundo pago', 'date' => 'second_payment_ends_on', 'event' => 'second_payment_event_id'],
    ];

    /**
     * Anticipación del recordatorio del evento del vencimiento, en minutos:
     * una semana. Es el recordatorio propio de Outlook, el del reloj.
     */
    private const REMINDER_MINUTES = 10080;

    public function __construct(private readonly CalendarEvents $events) {}

    /**
     * Pone al día los dos eventos: crea el que falta, reemplaza el que cambió
     * de fecha y quita el del semestre al que le borraron la vigencia.
     *
     * Se llama justo después de guardar la unidad: de ese guardado sale qué
     * fechas se movieron.
     *
     * Devuelve false si algo no se pudo agendar, para poder decirlo en pantalla.
     */
    public function sync(Unit $unit, User $actor): bool
    {
        if (! $this->events->available($actor)) {
            return false;
        }

        // Se toma antes del ciclo: guardar el id de un evento es otro save y
        // borraría de getChanges() lo que cambió en la edición.
        $changed = array_keys($unit->getChanges());

        // Limpieza de la versión anterior: antes cada aviso era un evento
        // aparte. Ahora el aviso vive dentro del evento del vencimiento, así
        // que los sueltos se borran la próxima vez que se guarda la unidad.
        $ok = $this->forgetReminders($unit, $actor);

        foreach (self::PAYMENTS as $payment) {
            $ok = $this->syncPayment($unit, $actor, $payment, in_array($payment['date'], $changed, true)) && $ok;
        }

        return $ok;
    }

    /** Quita de Outlook los eventos de la unidad, si los hay. */
    public function forget(Unit $unit, User $actor): bool
    {
        if (! $this->events->available($actor)) {
            return false;
        }

        $ok = $this->forgetReminders($unit, $actor);

        foreach (self::PAYMENTS as $payment) {
            $ok = $this->forgetEvent($unit, $actor, $payment['event']) && $ok;
        }

        return $ok;
    }

    /**
     * @param  array{label: string, date: string, event: string}  $payment
     */
    private function syncPayment(Unit $unit, User $actor, array $payment, bool $dateChanged): bool
    {
        $date = $unit->{$payment['date']};

        // Sin fecha de vencimiento no hay nada que agendar: si había evento,
        // se quita en vez de dejarlo colgado en una fecha que ya no existe.
        if (! $date) {
            return $this->forgetEvent($unit, $actor, $payment['event']);
        }

        // Misma fecha y evento ya agendado: no se toca. Cualquier cambio al
        // evento hace que Outlook reenvíe la invitación a todos los invitados.
        if ($unit->{$payment['event']} && ! $dateChanged) {
            return true;
        }

        // La fecha se movió: el evento viejo se cancela (al borrarlo, Outlook
        // avisa la cancelación a los invitados) y se agenda uno nuevo. Si ya
        // no existía en Outlook, igual se crea el nuevo.
        if ($unit->{$payment['event']}) {
            try {
                $this->events->delete($actor, $unit->{$payment['event']}, notify: false);
            } catch (Throwable $e) {
                report($e);
            }

            // Si el nuevo no se llega a crear, el siguiente guardado lo intenta
            // otra vez en lugar de creer que ya hay uno.
            $unit->forceFill([$payment['event'] => null])->save();
        }

        try {
            $event = $this->events->create(
                $actor,
                $this->event($unit, $payment['label'], $date),
                // Con invitados, igual que las licencias: así Outlook manda la
                // invitación de cada vencimiento. Son dos correos por unidad
                // porque son dos fechas límite distintas.
                notify: false,
            );
        } catch (Throwable $e) {
            report($e);

            return false;
        }

        $unit->forceFill([$payment['event'] => $event['id']])->save();

        return true;
    }

    /**
     * Borra los eventos-aviso que dejó la versión anterior.
     *
     * Se puede quitar, junto con la tabla `unit_reminders`, cuando ya no
     * queden renglones.
     */
    private function forgetReminders(Unit $unit, User $actor): bool
    {
        $reminders = $unit->reminders()->get();

        $ok = true;

        foreach ($reminders as $reminder) {
            try {
                $this->events->delete($actor, $reminder->event_id, notify: false);
            } catch (Throwable $e) {
                report($e);

                $ok = false;

                continue;
            }

            $reminder->delete();
        }

        $unit->unsetRelation('reminders');

        return $ok;
    }

    private function forgetEvent(Unit $unit, User $actor, string $column): bool
    {
        if (! $unit->{$column}) {
            return true;
        }

        try {
            $this->events->delete($actor, $unit->{$column}, notify: false);
        } catch (Throwable $e) {
            report($e);

            return false;
        }

        // El registro puede venir en camino a borrarse: solo se limpia si sigue.
        if ($unit->exists) {
            $unit->forceFill([$column => null])->save();
        }

        return true;
    }

    /**
     * Lo que se ve en Outlook. De todo el día y en la fecha de vencimiento:
     * ese es el último día para pagar, no un horario de nada.
     *
     * @return array<string, mixed>
     */
    private function event(Unit $unit, string $label, Carbon $date): array
    {
        $lines = array_filter([
            'Unidad: '.$unit->brand.' '.$unit->model,
            'Póliza: '.$unit->policy,
            $unit->economic_number ? "Económico: {$unit->economic_number}" : null,
            $unit->plate ? "Placa: {$unit->plate}" : null,
            $unit->businessUnit?->name ? 'Unidad de negocio: '.$unit->businessUnit->name : null,
            $unit->responsible ? "Responsable: {$unit->responsible}" : null,
        ]);

        $day = $date->copy()->startOfDay();

        return [
            'title' => "{$label} · Póliza {$unit->policy} · {$unit->brand} {$unit->model}",
            'description' => implode("\n", $lines),
            // La unidad de negocio va en la descripción: en «Ubicación» Outlook
            // la muestra como si fuera un lugar.
            'location' => null,
            'all_day' => true,
            'start' => $day,
            'end' => $day->copy(),
            // El recordatorio de Outlook: salta una semana antes en el buzón
            // de quien lo tenga, sin que el servidor mande nada.
            'reminder_minutes' => self::REMINDER_MINUTES,
        ];
    }
}
