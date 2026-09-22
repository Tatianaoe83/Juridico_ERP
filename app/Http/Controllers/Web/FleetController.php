<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fleet\StoreUnitRequest;
use App\Http\Requests\Fleet\UpdateUnitRequest;
use App\Models\BusinessUnit;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
     * GET /flotillas/crear
     *
     * El alta va en su propia vista y no en un modal: son más de quince
     * campos, dos rangos de pago y archivos.
     */
    public function create(): Response
    {
        return Inertia::render('Fleets/Create', [
            'businessUnits' => BusinessUnit::orderBy('name')->get(['id', 'name']),
            'statuses' => Unit::STATUSES,
        ]);
    }

    /** POST /flotillas */
    public function store(StoreUnitRequest $request): RedirectResponse
    {
        $data = $request->validated();

        // Los archivos no son columnas: se guardan aparte, ya con la unidad creada.
        unset($data['evidences']);

        $unit = Unit::create([
            ...$data,
            'created_by' => $request->user()->id,
        ]);

        $this->storeEvidences($request, $unit);

        return to_route('fleets.index')->with('success', "Se registró la unidad {$unit->policy}.");
    }

    /**
     * GET /flotillas/{unit}/editar
     *
     * Trae la unidad con sus valores tal como se capturan —fechas en Y-m-d e
     * importes en número— para que el formulario los cargue sin traducir nada.
     */
    public function edit(Unit $unit): Response
    {
        return Inertia::render('Fleets/Edit', [
            'unit' => [
                'id' => $unit->id,
                'policy' => $unit->policy,
                'certificate' => $unit->certificate,
                'business_unit_id' => $unit->business_unit_id,
                'brand' => $unit->brand,
                'model' => $unit->model,
                'serial_number' => $unit->serial_number,
                'plate' => $unit->plate,
                'economic_number' => $unit->economic_number,
                'responsible' => $unit->responsible,
                'first_payment_starts_on' => $unit->first_payment_starts_on?->toDateString(),
                'first_payment_ends_on' => $unit->first_payment_ends_on?->toDateString(),
                'first_payment_amount' => $unit->first_payment_amount,
                'second_payment_starts_on' => $unit->second_payment_starts_on?->toDateString(),
                'second_payment_ends_on' => $unit->second_payment_ends_on?->toDateString(),
                'second_payment_amount' => $unit->second_payment_amount,
                'usa_canada_endorsement' => $unit->usa_canada_endorsement,
                'status' => $unit->status,
                'comments' => $unit->comments,
                // Para poder quitarlas desde el formulario; el archivo en sí
                // no se manda, solo con qué nombre y peso se subió.
                'evidences' => $unit->evidences()->orderBy('id')->get(['id', 'name', 'size']),
            ],
            'businessUnits' => BusinessUnit::orderBy('name')->get(['id', 'name']),
            'statuses' => Unit::STATUSES,
        ]);
    }

    /** PATCH /flotillas/{unit} */
    public function update(UpdateUnitRequest $request, Unit $unit): RedirectResponse
    {
        $data = $request->validated();

        // Los archivos no son columnas: se manejan aparte.
        unset($data['evidences'], $data['remove_evidences']);

        $unit->update($data);

        $this->forgetEvidences($request, $unit);
        $this->storeEvidences($request, $unit);

        return to_route('fleets.index')->with('success', "Se actualizó la unidad {$unit->policy}.");
    }

    /**
     * Borra las evidencias que se quitaron en el formulario: primero el
     * archivo del disco y luego el registro, para no dejar basura colgando.
     *
     * El FormRequest ya comprobó que cada id es de esta unidad.
     */
    private function forgetEvidences(UpdateUnitRequest $request, Unit $unit): void
    {
        $ids = $request->validated('remove_evidences') ?? [];

        foreach ($unit->evidences()->whereIn('id', $ids)->get() as $evidence) {
            Storage::disk('local')->delete($evidence->path);
            $evidence->delete();
        }
    }

    /**
     * Guarda los archivos en disco y deja en la base su ruta y el nombre con
     * el que los subieron, que es el que se va a descargar.
     */
    private function storeEvidences(StoreUnitRequest|UpdateUnitRequest $request, Unit $unit): void
    {
        foreach ($request->file('evidences', []) as $file) {
            /** @var UploadedFile $file */
            $unit->evidences()->create([
                'path' => Storage::disk('local')->putFile("units/{$unit->id}", $file),
                'name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
                'uploaded_by' => $request->user()->id,
            ]);
        }
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
