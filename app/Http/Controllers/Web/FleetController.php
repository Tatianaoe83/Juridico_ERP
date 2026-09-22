<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\BusinessUnit;
use App\Models\Unit;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Flotillas (Cumplimiento): las unidades con su póliza y sus dos pagos
 * semestrales. Por ahora solo el listado; el alta y la edición van después.
 */
class FleetController extends Controller
{
    private const PER_PAGE = 10;

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        // Un estado que no existe se ignora en vez de dejar la tabla vacía.
        $status = in_array($request->query('status'), Unit::STATUSES, true) ? $request->query('status') : null;

        // La marca se elige de lo ya capturado, así que se compara completa.
        $brand = trim((string) $request->query('brand', '')) ?: null;

        // La unidad de negocio viene del catálogo: se filtra por su id.
        $businessUnit = (int) $request->query('business_unit') ?: null;

        $units = Unit::with(['creator', 'businessUnit'])
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('policy', 'like', "%{$search}%")
                ->orWhere('brand', 'like', "%{$search}%")
                ->orWhere('model', 'like', "%{$search}%")
                ->orWhereHas('businessUnit', fn ($q2) => $q2->where('name', 'like', "%{$search}%"))
                ->orWhere('plate', 'like', "%{$search}%")
                ->orWhere('economic_number', 'like', "%{$search}%")
                ->orWhere('responsible', 'like', "%{$search}%")))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($brand, fn ($query) => $query->where('brand', $brand))
            ->when($businessUnit, fn ($query) => $query->where('business_unit_id', $businessUnit))
            ->orderBy('economic_number')
            ->orderBy('policy')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('Fleets/Index', [
            'units' => [
                'data' => $units->getCollection()->map(fn (Unit $unit) => $this->summary($unit)),
                'meta' => [
                    'total' => $units->total(),
                    'per_page' => $units->perPage(),
                    'current_page' => $units->currentPage(),
                    'last_page' => $units->lastPage(),
                ],
            ],
            'stats' => $this->stats(),
            'filters' => [
                'search' => $search,
                'status' => $status,
                'brand' => $brand,
                'business_unit' => $businessUnit ? (string) $businessUnit : null,
            ],
            'options' => [
                // Las marcas salen de lo capturado: nadie mantiene catálogo y
                // nunca se ofrece un filtro que no devuelva nada.
                'brands' => $this->distinct('brand'),
                // Las unidades de negocio sí son catálogo: van todas, aunque
                // todavía no tengan unidades asignadas.
                'businessUnits' => BusinessUnit::orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn (BusinessUnit $unit) => ['value' => (string) $unit->id, 'label' => $unit->name])
                    ->all(),
            ],
        ]);
    }

    /**
     * Lo que necesita la tabla y el modal de detalle, ya calculado: el costo
     * anual y el IVA salen de los dos pagos, no de una columna.
     *
     * @return array<string, mixed>
     */
    private function summary(Unit $unit): array
    {
        return [
            'id' => $unit->id,
            'policy' => $unit->policy,
            'certificate' => $unit->certificate,
            'business_unit' => $unit->businessUnit?->name,
            'business_unit_id' => $unit->business_unit_id,
            'brand' => $unit->brand,
            'model' => $unit->model,
            'serial_number' => $unit->serial_number,
            'plate' => $unit->plate,
            'economic_number' => $unit->economic_number,
            'responsible' => $unit->responsible,
            'first_payment' => [
                'starts_on' => $unit->first_payment_starts_on?->toDateString(),
                'ends_on' => $unit->first_payment_ends_on?->toDateString(),
                'amount' => (float) $unit->first_payment_amount,
            ],
            'second_payment' => [
                'starts_on' => $unit->second_payment_starts_on?->toDateString(),
                'ends_on' => $unit->second_payment_ends_on?->toDateString(),
                'amount' => (float) $unit->second_payment_amount,
            ],
            'annual_cost' => $unit->annualCost(),
            'tax' => $unit->tax(),
            'total' => $unit->total(),
            'next_payment' => $unit->nextPaymentDate()?->toDateString(),
            'usa_canada_endorsement' => $unit->usa_canada_endorsement,
            'status' => $unit->status,
            'comments' => $unit->comments,
            'created_by' => $unit->creator?->name,
            'created_at' => $unit->created_at?->toIso8601String(),
        ];
    }

    /**
     * Valores distintos de una columna, para llenar un filtro.
     *
     * @return list<string>
     */
    private function distinct(string $column): array
    {
        return Unit::query()
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->distinct()
            ->orderBy($column)
            ->pluck($column)
            ->all();
    }

    /**
     * El resumen es de toda la flotilla: no cambia con la búsqueda ni el filtro.
     *
     * @return array<string, int>
     */
    private function stats(): array
    {
        $today = today();
        $limit = $today->copy()->addDays(Unit::WARNING_DAYS);

        return [
            'total' => Unit::query()->count(),
            // Por vencer: cualquiera de los dos pagos cae dentro de la ventana.
            'payments' => Unit::query()
                ->where(fn ($query) => $query
                    ->whereBetween('first_payment_starts_on', [$today, $limit])
                    ->orWhereBetween('second_payment_starts_on', [$today, $limit]))
                ->count(),
            'maintenance' => Unit::query()->where('status', 'maintenance')->count(),
        ];
    }
}
