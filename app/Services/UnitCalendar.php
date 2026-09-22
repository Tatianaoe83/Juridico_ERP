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

    public function __construct(private readonly CalendarEvents $events) {}

    /**
     * Pone al día los dos eventos: crea el que falta, mueve el que cambió de
     * fecha y quita el del semestre al que le borraron la vigencia.
     *
     * Devuelve false si algo no se pudo agendar, para poder decirlo en pantalla.
     */
    public function sync(Unit $unit, User $actor): bool
    {
        if (! $this->events->available($actor)) {
            return false;
        }

        $ok = true;

        foreach (self::PAYMENTS as $payment) {
            $ok = $this->syncPayment($unit, $actor, $payment) && $ok;
        }

        return $ok;
    }

    /** Quita de Outlook los eventos de la unidad, si los hay. */
    public function forget(Unit $unit, User $actor): bool
    {
        if (! $this->events->available($actor)) {
            return false;
        }

        $ok = true;

        foreach (self::PAYMENTS as $payment) {
            $ok = $this->forgetEvent($unit, $actor, $payment['event']) && $ok;
        }

        return $ok;
    }

    /**
     * @param  array{label: string, date: string, event: string}  $payment
     */
    private function syncPayment(Unit $unit, User $actor, array $payment): bool
    {
        $date = $unit->{$payment['date']};

        // Sin fecha de vencimiento no hay nada que agendar: si había evento,
        // se quita en vez de dejarlo colgado en una fecha que ya no existe.
        if (! $date) {
            return $this->forgetEvent($unit, $actor, $payment['event']);
        }

        try {
            $event = $this->events->sync(
                $actor,
                $unit->{$payment['event']},
                $this->event($unit, $payment['label'], $date),
                notify: false,
            );
        } catch (Throwable $e) {
            report($e);

            return false;
        }

        $unit->forceFill([$payment['event'] => $event['id']])->save();

        return true;
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
            'reminder_minutes' => null,
        ];
    }
}
