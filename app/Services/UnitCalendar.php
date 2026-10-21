<?php

namespace App\Services;

use App\Models\Unit;
use App\Models\UnitPolicy;
use App\Models\User;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Refleja cada póliza en el calendario de Outlook: la fecha límite de cada
 * pago y el fin de la vigencia quedan agendados sin capturarlos a mano.
 *
 * Aquí solo se arma el evento; cómo se inserta lo resuelve CalendarEvents.
 *
 * Nada de esto puede tumbar el alta: si Graph falla o la cuenta no está
 * vinculada, la póliza se guarda igual y el evento sencillamente no se crea.
 * Por eso los métodos devuelven bool en vez de lanzar.
 */
class UnitCalendar
{
    /** Las columnas donde se guarda el id de cada evento de la póliza. */
    private const EVENT_COLUMNS = ['first_payment_event_id', 'second_payment_event_id', 'validity_event_id'];

    /**
     * Las fechas de cada póliza. No se agendan pagos: la empresarial
     * vehicular, el cierre de cada semestre (vigencia 1 y 2, cada uno en el
     * lugar de su pago); las que no tienen semestres, solo su fin. El lugar
     * del segundo pago queda sin fecha: si tenía evento, se quita.
     *
     * @return array<int, array{label: string, date: ?string, event: string}>
     */
    public static function entriesFor(UnitPolicy $policy): array
    {
        $semesters = $policy->first_payment_ends_on !== null;

        return [
            ['label' => 'Fin de vigencia 1', 'date' => 'first_payment_ends_on', 'event' => 'first_payment_event_id'],
            ['label' => 'Sin uso', 'date' => null, 'event' => 'second_payment_event_id'],
            ['label' => $semesters ? 'Fin de vigencia 2' : 'Fin de vigencia', 'date' => 'second_payment_ends_on', 'event' => 'validity_event_id'],
        ];
    }

    /**
     * Si al guardar hay que tocar Outlook: se movió alguna fecha o le falta un
     * evento. Actualizarlo le vuelve a llegar el aviso a los compartidos, así
     * que si nada cambió no se toca.
     */
    public static function needsSync(UnitPolicy $policy): bool
    {
        $entries = collect(self::entriesFor($policy))->filter(fn (array $entry) => $entry['date'] !== null);

        return $policy->wasRecentlyCreated
            || $policy->wasChanged($entries->pluck('date')->all())
            || $entries->contains(fn (array $entry) => $policy->{$entry['date']} && ! $policy->{$entry['event']});
    }

    /**
     * Anticipación del recordatorio de cada evento, en minutos: una semana.
     * Es el recordatorio propio de Outlook, el del reloj.
     */
    private const REMINDER_MINUTES = 10080;

    public function __construct(private readonly CalendarEvents $events) {}

    /**
     * Pone al día los eventos de la póliza: crea el que falta, mueve el
     * que cambió de fecha y quita el de la fecha que se borró.
     *
     * Se llama justo después de guardar la póliza: de ese guardado sale qué
     * fechas se movieron.
     *
     * Devuelve false si algo no se pudo agendar, para poder decirlo en pantalla.
     */
    public function sync(UnitPolicy $policy, User $actor): bool
    {
        if (! $this->events->available($actor)) {
            return false;
        }

        // Se toma antes del ciclo: guardar el id de un evento es otro save y
        // borraría de getChanges() lo que cambió en la edición.
        $changed = array_keys($policy->getChanges());

        $policy->loadMissing('unit.businessUnit');

        // Limpieza de la versión anterior: antes cada aviso era un evento
        // aparte. Ahora el aviso vive dentro del evento, así que los sueltos
        // se borran la próxima vez que se guarda la póliza.
        $ok = $policy->unit ? $this->forgetReminders($policy->unit, $actor) : true;

        foreach (self::entriesFor($policy) as $entry) {
            $ok = $this->syncEvent($policy, $actor, $entry, in_array($entry['date'], $changed, true)) && $ok;
        }

        return $ok;
    }

    /** Quita de Outlook los eventos de la póliza, si los hay. */
    public function forgetPolicy(UnitPolicy $policy, User $actor): bool
    {
        if (! $this->events->available($actor)) {
            return false;
        }

        $ok = true;

        foreach (self::EVENT_COLUMNS as $column) {
            $ok = $this->forgetEvent($policy, $actor, $column) && $ok;
        }

        return $ok;
    }

    /**
     * Quita de Outlook los eventos del periodo vigente de la unidad. Los de
     * periodos anteriores se quedan: son el registro de lo que ya pasó.
     */
    public function forget(Unit $unit, User $actor): bool
    {
        if (! $this->events->available($actor)) {
            return false;
        }

        $ok = $this->forgetReminders($unit, $actor);

        $policy = $unit->currentPolicy;

        return $policy ? $this->forgetPolicy($policy, $actor) && $ok : $ok;
    }

    /**
     * @param  array{label: string, date: ?string, event: string}  $entry
     */
    private function syncEvent(UnitPolicy $policy, User $actor, array $entry, bool $dateChanged): bool
    {
        $date = $entry['date'] ? $policy->{$entry['date']} : null;

        // Sin fecha no hay nada que agendar: si había evento, se quita en vez
        // de dejarlo colgado en una fecha que ya no existe.
        if (! $date) {
            return $this->forgetEvent($policy, $actor, $entry['event']);
        }

        // Misma fecha y evento ya agendado: no se toca. Cualquier cambio al
        // evento hace que Outlook reenvíe la invitación a los compartidos.
        if ($policy->{$entry['event']} && ! $dateChanged) {
            return true;
        }

        try {
            // Sin correo propio: la invitación nativa de Outlook ya avisa a
            // los compartidos.
            $event = $this->events->sync($actor, $policy->{$entry['event']}, $this->event($policy, $entry['label'], $date), notify: false);
        } catch (Throwable $e) {
            report($e);

            return false;
        }

        $policy->forceFill([$entry['event'] => $event['id']])->save();

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

    private function forgetEvent(UnitPolicy $policy, User $actor, string $column): bool
    {
        if (! $policy->{$column}) {
            return true;
        }

        try {
            $this->events->delete($actor, $policy->{$column}, notify: false);
        } catch (Throwable $e) {
            report($e);

            return false;
        }

        // El registro puede venir en camino a borrarse: solo se limpia si sigue.
        if ($policy->exists) {
            $policy->forceFill([$column => null])->save();
        }

        return true;
    }

    /**
     * Lo que se ve en Outlook. De todo el día: es el último día para pagar o
     * el día que se acaba la vigencia, no un horario de nada.
     *
     * @return array<string, mixed>
     */
    private function event(UnitPolicy $policy, string $label, Carbon $date): array
    {
        $unit = $policy->unit;
        $unitName = trim("{$unit?->brand} {$unit?->model}");
        $businessUnit = $policy->businessUnitName();

        // La de maquinaria cubre varias máquinas. El evento solo lleva la
        // póliza y su unidad de negocio: las máquinas cambian sin mover fechas
        // y el evento no se vuelve a tocar, así que quedarían desactualizadas.
        if ($policy->isMachinery()) {
            $unitName = '';
        }

        $lines = array_filter([
            "Póliza: {$policy->policy}",
            $policy->insurer ? "Aseguradora: {$policy->insurer->name}" : null,
            $policy->supplier ? "Beneficiario preferente: {$policy->supplier->name}" : null,
            $unitName ? "Unidad: {$unitName}" : null,
            $unit?->plate ? "Placa: {$unit->plate}" : null,
            $unit?->economic_number ? "Económico: {$unit->economic_number}" : null,
            $businessUnit ? "Unidad de negocio: {$businessUnit}" : null,
            $unit?->responsible ? "Responsable: {$unit->responsible}" : null,
        ]);

        $day = $date->copy()->startOfDay();

        return [
            'title' => collect([$label, "Póliza {$policy->policy}", $unitName])->filter()->implode(' · '),
            'description' => implode("\n", $lines),
            // La unidad de negocio va en la descripción: en «Ubicación» Outlook
            // la muestra como si fuera un lugar.
            'location' => null,
            'all_day' => true,
            'start' => $day,
            'end' => $day->copy(),
            // La alerta la lanza Outlook en cada buzón; el servidor no manda nada.
            'reminder_minutes' => self::REMINDER_MINUTES,
        ];
    }
}
