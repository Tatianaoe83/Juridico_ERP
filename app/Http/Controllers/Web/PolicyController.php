<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Bond;
use App\Models\UnitPolicy;
use App\Support\CoverageStatus;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pólizas y Fianzas (Cumplimiento): una sola tabla con las dos coberturas.
 * Cada renglón dice qué es, quién la da, sobre qué, su vigencia, la suma y
 * cómo va. Por ahora solo la tabla.
 */
class PolicyController extends Controller
{
    private const PER_PAGE = 10;

    public const TYPES = ['policy', 'bond'];

    /**
     * Lo más urgente arriba: vencidas, por vencer, en cancelación, vigentes,
     * sin vigencia y al final las canceladas.
     */
    private const PRIORITY = [
        CoverageStatus::EXPIRED,
        CoverageStatus::EXPIRING,
        CoverageStatus::CANCELLING,
        CoverageStatus::ACTIVE,
        CoverageStatus::UNKNOWN,
        CoverageStatus::CANCELLED,
    ];

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        // Un tipo que no existe se ignora en vez de dejar la tabla vacía.
        $type = in_array($request->query('type'), self::TYPES, true) ? $request->query('type') : null;

        // Son decenas, no miles: se juntan aquí y se pagina sobre la lista.
        $all = $this->policies()->concat($this->bonds());

        $rows = $all
            ->when($type, fn (Collection $rows) => $rows->where('type', $type))
            ->when($search !== '', fn (Collection $rows) => $rows->filter(fn (array $row) => $this->matches($row, $search)))
            ->sortBy([
                fn (array $a, array $b) => array_search($a['status'], self::PRIORITY) <=> array_search($b['status'], self::PRIORITY),
                // Sin fecha de fin, al final de su grupo.
                fn (array $a, array $b) => ($a['valid_until'] ?? '9999-12-31') <=> ($b['valid_until'] ?? '9999-12-31'),
            ])
            ->values();

        $page = LengthAwarePaginator::resolveCurrentPage();
        $paginator = new LengthAwarePaginator($rows->forPage($page, self::PER_PAGE)->values(), $rows->count(), self::PER_PAGE, $page);

        return Inertia::render('Policies/Index', [
            'coverages' => [
                'data' => $paginator->getCollection()->map(fn (array $row) => collect($row)->except('search')->all()),
                'meta' => [
                    'total' => $paginator->total(),
                    'per_page' => $paginator->perPage(),
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                ],
            ],
            'stats' => $this->stats($all),
            'filters' => ['search' => $search, 'type' => $type],
        ]);
    }

    /**
     * Las pólizas de las unidades, solo el periodo vigente de cada una: los
     * renovados son historial y se ven en la ficha de la unidad.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function policies(): Collection
    {
        return UnitPolicy::with('unit:id,brand,model,plate,economic_number')
            ->whereDoesntHave('renewal')
            ->get()
            ->map(function (UnitPolicy $policy) {
                $unit = $policy->unit;
                $due = $policy->nextDue();
                $unitName = $unit ? trim("{$unit->brand} {$unit->model}") : null;

                return [
                    'key' => "policy-{$policy->id}",
                    'type' => 'policy',
                    'id' => $policy->id,
                    'number' => $policy->policy,
                    'provider' => $policy->insurer,
                    // Sobre qué: la unidad asegurada, con placa y número económico.
                    'subject' => $unitName,
                    'detail' => collect([$unit?->plate, $unit?->economic_number])->filter()->implode(' · ') ?: null,
                    'unit_id' => $unit?->id,
                    'valid_from' => $policy->first_payment_starts_on?->toDateString(),
                    'valid_until' => $policy->second_payment_ends_on?->toDateString(),
                    'amount' => $policy->annualCost(),
                    'status' => $policy->coverageStatus(),
                    // Las dos cuotas: cuál ya se pagó y la fecha límite de la que
                    // sigue. Cancelada ya no hay cuotas que seguir.
                    'payments' => $policy->coverageStatus() === CoverageStatus::CANCELLED ? null : [
                        'paid' => collect(UnitPolicy::PAYMENTS)->mapWithKeys(fn (string $payment) => [$payment => $policy->isPaid($payment)])->all(),
                        'due' => $due ? [
                            'payment' => $due['payment'],
                            'due_on' => $due['due_on']?->toDateString(),
                            'status' => $due['status'],
                        ] : null,
                    ],
                    'search' => [$policy->policy, $policy->certificate, $policy->insurer, $unitName, $unit?->plate, $unit?->economic_number],
                ];
            });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function bonds(): Collection
    {
        return Bond::query()
            ->get()
            ->map(fn (Bond $bond) => [
                'key' => "bond-{$bond->id}",
                'type' => 'bond',
                'id' => $bond->id,
                'number' => $bond->bond,
                'provider' => $bond->bonding_company,
                // Sobre qué: el tipo de fianza, o el beneficiario si no lo tiene.
                'subject' => $bond->product ?? $bond->beneficiary,
                'detail' => $bond->product ? $bond->beneficiary : $bond->related,
                'unit_id' => null,
                'valid_from' => $bond->valid_from?->toDateString(),
                'valid_until' => $bond->valid_until?->toDateString(),
                'amount' => (float) $bond->amount,
                'status' => $bond->status(),
                'payments' => null,
                'search' => [$bond->bond, $bond->bonding_company, $bond->beneficiary, $bond->product, $bond->related, $bond->source_document],
            ]);
    }

    /** @param  array<string, mixed>  $row */
    private function matches(array $row, string $search): bool
    {
        return collect($row['search'])->filter()->contains(fn (string $value) => mb_stripos($value, $search) !== false);
    }

    /**
     * Las tarjetas son de todo, no cambian con la búsqueda ni el filtro. Las
     * canceladas y las que van en cancelación no cuentan en ninguna.
     *
     * @param  Collection<int, array<string, mixed>>  $all
     * @return array<string, int>
     */
    private function stats(Collection $all): array
    {
        $count = fn (string ...$statuses) => $all->whereIn('status', $statuses)->count();

        return [
            'active' => $count(CoverageStatus::ACTIVE),
            'expiring' => $count(CoverageStatus::EXPIRING),
            'expired' => $count(CoverageStatus::EXPIRED),
            'warning_days' => CoverageStatus::WARNING_DAYS,
        ];
    }
}
