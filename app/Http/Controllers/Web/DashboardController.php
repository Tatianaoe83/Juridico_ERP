<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Bond;
use App\Models\ConstructionPolicy;
use App\Models\License;
use App\Models\Unit;
use App\Models\UnitEvidence;
use App\Models\UnitPolicy;
use App\Support\CoverageStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Panel de inicio: lo que pide atención en licencias, flotillas y pólizas.
 *
 * Cada sección sale solo si el usuario puede ver su módulo; si no, va null y
 * el front la oculta. Así nadie ve aquí datos que su rol no le deja abrir.
 */
class DashboardController extends Controller
{
    /** Lo urgente primero: lo vencido antes que lo que está por vencer. */
    private const PRIORITY = ['overdue', 'expired', 'expiring', 'pending'];

    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        $canFleets = $user->can('flotillas.view');
        $canPolicies = $user->can('polizas.view');

        $licenses = $user->can('licencias.view') ? $this->licenses() : null;
        $fleets = $canFleets ? $this->fleets() : null;
        // shortcut: pagos de flotilla ocultos hasta que exista su función en flotillas; volver a $canFleets ? $this->payments() : null.
        $payments = null;
        $policies = $canPolicies ? $this->policies() : null;
        $documents = $canFleets || $canPolicies ? $this->documents($canFleets, $canPolicies) : null;

        return Inertia::render('Dashboard/Index', [
            'licenses' => $licenses ? $this->section($licenses) : null,
            'fleets' => $fleets ? $this->section($fleets) : null,
            'payments' => $payments ? $this->section($payments) : null,
            'policies' => $policies ? $this->section($policies) : null,
            // Van todos: el card de documentos desplaza lo que no quepa.
            'documents' => $documents ? $this->section($documents) : null,
            'alerts' => $this->alerts($user->can('licencias.view'), $canFleets, $canPolicies),
            'warning_days' => CoverageStatus::WARNING_DAYS,
        ]);
    }

    /**
     * Alertas activas: registros con el recordatorio recurrente prendido que
     * aún no vencen, o sea, que todavía van a mandar correo. Mismos filtros que
     * SendExpirationReminders, sin la fecha del día. Solo de los módulos que
     * el usuario puede ver.
     */
    private function alerts(bool $licenses, bool $fleets, bool $policies): int
    {
        $today = today();

        return ($licenses ? License::where('reminders_enabled', true)->whereDate('valid_until', '>=', $today)->count() : 0)
            + ($fleets ? UnitPolicy::where('reminders_enabled', true)->whereNull('cancelled_on')->whereDate('second_payment_ends_on', '>=', $today)->count() : 0)
            + ($policies ? ConstructionPolicy::where('reminders_enabled', true)->whereDate('valid_until', '>=', $today)->count() : 0)
            + ($policies ? Bond::where('reminders_enabled', true)->whereNull('cancelled_on')->whereDate('valid_until', '>=', $today)->count() : 0);
    }

    /** Conteo por estado y la lista, ordenada por urgencia y fecha. */
    private function section(Collection $rows): array
    {
        return [
            'total' => $rows->count(),
            'counts' => $rows->countBy('status')->all(),
            'items' => $rows
                ->sortBy([
                    fn (array $a, array $b) => array_search($a['status'], self::PRIORITY) <=> array_search($b['status'], self::PRIORITY),
                    fn (array $a, array $b) => ($a['date'] ?? '9999-12-31') <=> ($b['date'] ?? '9999-12-31'),
                ])
                ->values(),
        ];
    }

    /** Licencias por vencer: la misma regla que su módulo. */
    private function licenses(): Collection
    {
        return License::with('businessUnit:id,name')
            ->whereDate('valid_until', '>=', today())
            ->whereDate('valid_until', '<=', today()->addDays(License::WARNING_DAYS))
            ->get()
            ->map(fn (License $license) => [
                'key' => "license-{$license->id}",
                'title' => $license->name,
                'subtitle' => $license->businessUnit?->name ?? $license->authority,
                'date' => $license->valid_until->toDateString(),
                'status' => $license->status(),
                'href' => '/licencias?search='.urlencode($license->name),
            ]);
    }

    /**
     * Pólizas de flotilla por vencer, con la misma regla que el resumen de su
     * módulo: algún semestre del periodo vigente cierra dentro de la ventana; en
     * las de maquinaria y las del proveedor, el fin de vigencia.
     */
    private function fleets(): Collection
    {
        $today = today();
        $limit = $today->copy()->addDays(Unit::WARNING_DAYS);

        return UnitPolicy::with('unit:id,brand,model,plate,economic_number')
            ->whereIn('kind', UnitPolicy::KINDS)
            ->whereDoesntHave('renewal')
            ->whereNull('cancelled_on')
            ->where(fn ($query) => $query
                ->whereBetween('first_payment_ends_on', [$today, $limit])
                ->orWhereBetween('second_payment_ends_on', [$today, $limit]))
            ->get()
            ->map(function (UnitPolicy $policy) use ($today) {
                $first = $policy->first_payment_ends_on?->gte($today);

                return [
                    'key' => "fleet-{$policy->id}",
                    'title' => $this->unitName($policy),
                    'subtitle' => "Póliza {$policy->policy} · ".($first ? 'fin del 1er semestre' : 'fin de vigencia'),
                    'date' => ($first ? $policy->first_payment_ends_on : $policy->second_payment_ends_on)->toDateString(),
                    'status' => 'expiring',
                    'href' => $this->fleetUrl($policy),
                ];
            });
    }

    /**
     * Pagos de flotilla sin cubrir del periodo vigente: vencidos o dentro de la
     * ventana de aviso. Son decenas de pólizas, así que se resuelve en PHP con
     * la misma regla que el módulo (nextDue). Solo las vehiculares
     * empresariales van por semestres; la maquinaria y la del proveedor llevan
     * un pago único sin cierre de semestre.
     */
    private function payments(): Collection
    {
        $limit = today()->addDays(Unit::WARNING_DAYS);

        return UnitPolicy::with('unit:id,brand,model,plate,economic_number')
            ->where('kind', UnitPolicy::VEHICLE)
            ->where('source', UnitPolicy::CORPORATE)
            ->whereDoesntHave('renewal')
            ->where(fn ($query) => $query->whereNull('first_payment_paid_at')->orWhereNull('second_payment_paid_at'))
            ->get()
            ->map(function (UnitPolicy $policy) {
                $due = $policy->nextDue();

                if (! $due) {
                    return null;
                }

                // Sin fecha límite de la aseguradora, manda el cierre del semestre.
                $date = $due['due_on'] ?? $policy->{"{$due['payment']}_payment_ends_on"};

                return [
                    'key' => "payment-{$policy->id}",
                    'title' => $this->unitName($policy),
                    'subtitle' => "Póliza {$policy->policy} · ".($due['payment'] === 'first' ? '1er' : '2º').' semestre',
                    'date' => $date?->toDateString(),
                    'status' => $date && $date->lt(today()) ? 'overdue' : 'pending',
                    'amount' => $policy->{"{$due['payment']}_payment_amount"},
                    'currency' => $policy->currency ?? 'MXN',
                    'href' => $this->fleetUrl($policy),
                ];
            })
            ->filter(fn (?array $row) => $row && $row['date'] && $row['date'] <= $limit->toDateString())
            ->values();
    }

    /** Pólizas de obra y fianzas por vencer. */
    private function policies(): Collection
    {
        $policies = ConstructionPolicy::with('project:id,name')
            ->get()
            ->map(fn (ConstructionPolicy $policy) => [
                'key' => "policy-{$policy->id}",
                'title' => "Póliza {$policy->policy}",
                'subtitle' => $policy->project?->name,
                'date' => $policy->valid_until?->toDateString(),
                'status' => $policy->status(),
                'href' => "/polizas/{$policy->id}",
            ]);

        $bonds = Bond::with('beneficiary:id,name')
            ->get()
            ->map(fn (Bond $bond) => [
                'key' => "bond-{$bond->id}",
                'title' => "Fianza {$bond->bond}",
                'subtitle' => $bond->beneficiary?->name,
                'date' => $bond->valid_until?->toDateString(),
                'status' => $bond->status(),
                'href' => "/polizas/fianzas/{$bond->id}",
            ]);

        return $policies->concat($bonds)
            ->where('status', CoverageStatus::EXPIRING)
            ->values();
    }

    /**
     * Lo que falta subir: en flotillas, los documentos oficiales; en obra, la
     * póliza misma. Las vencidas no cuentan. Los documentos oficiales los formularios los cuelgan de la unidad (o de
     * cada máquina), no de la póliza: se buscan en los dos lados.
     */
    private function documents(bool $fleets, bool $policies): Collection
    {
        $rows = collect();

        if ($fleets) {
            UnitPolicy::with([
                'unit:id,brand,model,plate,economic_number',
                'unit.evidences:id,unit_id,type',
                'machines.evidences:id,unit_id,type',
                'evidences:id,unit_policy_id,type',
            ])
                ->whereIn('kind', UnitPolicy::KINDS)
                ->whereDoesntHave('renewal')
                ->whereNull('cancelled_on')
                ->get()
                // Se mide contra el fin de vigencia: con el 1er semestre vencido y el 2º vigente, sigue saliendo.
                ->reject(fn (UnitPolicy $policy) => $policy->coverageStatus() === CoverageStatus::EXPIRED)
                ->each(function (UnitPolicy $policy) use ($rows) {
                    $official = $policy->evidences
                        ->concat($policy->unit?->evidences ?? [])
                        ->concat($policy->machines->flatMap->evidences)
                        ->contains('type', UnitEvidence::OFFICIAL_DOCUMENT);

                    if (! $official) {
                        $rows->push([
                            'key' => "unit-policy-{$policy->id}",
                            'module' => 'fleets',
                            'type' => 'Flotilla',
                            'title' => $this->unitName($policy),
                            // La maquinaria ya trae la póliza en el título.
                            'subtitle' => $policy->unit ? "Póliza {$policy->policy}" : null,
                            'missing' => ['Documentos oficiales'],
                            'date' => null,
                            'status' => 'missing',
                            'href' => $this->fleetUrl($policy),
                        ]);
                    }
                });
        }

        if ($policies) {
            ConstructionPolicy::with('project:id,name')
                ->whereDoesntHave('documents')
                ->get()
                ->reject(fn (ConstructionPolicy $policy) => $policy->status() === CoverageStatus::EXPIRED)
                ->each(fn (ConstructionPolicy $policy) => $rows->push([
                    'key' => "policy-{$policy->id}",
                    'module' => 'policies',
                    'type' => 'Póliza de obra',
                    'title' => "Póliza {$policy->policy}",
                    'subtitle' => $policy->project?->name,
                    'missing' => ['Documento de la póliza'],
                    'date' => null,
                    'status' => 'missing',
                    'href' => "/polizas/{$policy->id}",
                ]));
        }

        return $rows;
    }

    /** Número económico, placa o marca y modelo; la maquinaria, por su póliza. */
    private function unitName(UnitPolicy $policy): string
    {
        $unit = $policy->unit;

        if (! $unit) {
            return "Maquinaria · póliza {$policy->policy}";
        }

        return $unit->economic_number ?: $unit->plate ?: trim("{$unit->brand} {$unit->model}");
    }

    private function fleetUrl(UnitPolicy $policy): string
    {
        return $policy->isMachinery() ? "/flotillas/maquinaria/{$policy->id}" : "/flotillas/{$policy->unit_id}";
    }
}
