<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fleet\PayUnitPolicyRequest;
use App\Http\Requests\Fleet\StoreUnitRequest;
use App\Http\Requests\Fleet\UpdateUnitRequest;
use App\Models\BusinessUnit;
use App\Models\Unit;
use App\Models\UnitEvidence;
use App\Models\UnitPolicy;
use App\Services\UnitCalendar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Flotillas (Cumplimiento): las unidades. Aquí solo se da de alta y se edita
 * el vehículo; sus pólizas se asignan aparte y la tabla solo las resume.
 */
class FleetController extends Controller
{
    private const PER_PAGE = 10;

    /**
     * Flotillas ya no agenda nada en Outlook. El calendario solo se usa al
     * eliminar, para limpiar los eventos que hayan quedado de antes.
     */
    public function __construct(private readonly UnitCalendar $calendar) {}

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        // Un estado que no existe se ignora en vez de dejar la tabla vacía.
        $status = in_array($request->query('status'), Unit::STATUSES, true) ? $request->query('status') : null;

        // La marca se elige de lo ya capturado, así que se compara completa.
        $brand = trim((string) $request->query('brand', '')) ?: null;

        // La unidad de negocio viene del catálogo: se filtra por su id.
        $businessUnit = (int) $request->query('business_unit') ?: null;

        $units = Unit::with('currentPolicy')
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q
                // En todos los periodos: una póliza vieja también lleva a su unidad.
                ->whereHas('policies', fn ($q2) => $q2->where('policy', 'like', "%{$search}%"))
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
            ->orderBy('id')
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
     * El alta va en su propia vista y no en un modal: son una decena de
     * campos y archivos.
     */
    public function create(): Response
    {
        return Inertia::render('Fleets/Create', [
            'businessUnits' => BusinessUnit::orderBy('name')->get(['id', 'name']),
            'statuses' => Unit::STATUSES,
        ]);
    }

    /** POST /flotillas: la unidad nace sin póliza, queda por asignar. */
    public function store(StoreUnitRequest $request): RedirectResponse
    {
        $unit = DB::transaction(function () use ($request) {
            $unit = Unit::create([
                ...Arr::except($request->validated(), ['evidences']),
                'created_by' => $request->user()->id,
            ]);

            $this->storeDocuments($request, $unit);

            return $unit;
        });

        return to_route('fleets.index')->with('success', "Se registró la unidad {$unit->brand} {$unit->model}.");
    }

    /**
     * GET /flotillas/{unit}
     *
     * El detalle del vehículo y sus documentos oficiales. Los pagos de la
     * póliza no van aquí: son del apartado de pólizas.
     */
    public function show(Unit $unit): Response
    {
        $unit->load(['businessUnit', 'currentPolicy']);

        $documents = $unit->evidences()
            ->where('type', UnitEvidence::OFFICIAL_DOCUMENT)
            ->with('uploader:id,name')
            ->orderBy('id')
            ->get();

        return Inertia::render('Fleets/Show', [
            'unit' => [
                ...$this->summary($unit),
                'business_unit' => $unit->businessUnit?->name,
                'comments' => $unit->comments,
                'documents' => $documents->map(fn (UnitEvidence $evidence) => [
                    'id' => $evidence->id,
                    'name' => $evidence->name,
                    'size' => $evidence->size,
                    'uploaded_by' => $evidence->uploader?->name,
                    'created_at' => $evidence->created_at?->toIso8601String(),
                ]),
            ],
        ]);
    }

    /**
     * GET /flotillas/{unit}/evidencias/{evidence}
     *
     * Los archivos viven fuera de public: se sirven por aquí, con el nombre
     * con el que se subieron y solo para quien puede ver flotillas.
     */
    public function evidence(Unit $unit, UnitEvidence $evidence): StreamedResponse
    {
        abort_unless($evidence->unit_id === $unit->id, 404);
        abort_unless(Storage::disk('local')->exists($evidence->path), 404);

        return Storage::disk('local')->download($evidence->path, $evidence->name);
    }

    /**
     * GET /flotillas/{unit}/editar
     *
     * Solo el vehículo, con los valores tal como se capturan. La póliza no
     * se ve ni se edita aquí.
     */
    public function edit(Unit $unit): Response
    {
        return Inertia::render('Fleets/Edit', [
            'unit' => [
                'id' => $unit->id,
                'business_unit_id' => $unit->business_unit_id,
                'brand' => $unit->brand,
                'model' => $unit->model,
                'serial_number' => $unit->serial_number,
                'plate' => $unit->plate,
                'economic_number' => $unit->economic_number,
                'responsible' => $unit->responsible,
                'status' => $unit->status,
                'comments' => $unit->comments,
                // Sus documentos oficiales, para poder quitarlos. Los
                // comprobantes de pago no se tocan desde aquí.
                'evidences' => $unit->evidences()
                    ->where('type', UnitEvidence::OFFICIAL_DOCUMENT)
                    ->orderBy('id')
                    ->get(['id', 'name', 'size']),
            ],
            'businessUnits' => BusinessUnit::orderBy('name')->get(['id', 'name']),
            'statuses' => Unit::STATUSES,
        ]);
    }

    /** PATCH /flotillas/{unit}: solo el vehículo; su póliza no se toca. */
    public function update(UpdateUnitRequest $request, Unit $unit): RedirectResponse
    {
        DB::transaction(function () use ($request, $unit) {
            $unit->update(Arr::except($request->validated(), ['evidences', 'remove_evidences']));

            $this->forgetEvidences($request, $unit);
            $this->storeDocuments($request, $unit);
        });

        return to_route('fleets.index')->with('success', "Se actualizó la unidad {$unit->brand} {$unit->model}.");
    }

    /**
     * POST /flotillas/{unit}/periodos/{policy}/pagos/{payment}
     *
     * Marca el semestre como pagado con su comprobante. No es un abono: vale
     * el importe del periodo. El segundo pago además renueva: se crea el
     * periodo siguiente con la póliza nueva.
     */
    public function pay(PayUnitPolicyRequest $request, Unit $unit, UnitPolicy $policy, string $payment): RedirectResponse
    {
        $renewal = DB::transaction(function () use ($request, $unit, $policy, $payment) {
            $this->storeEvidence($request, $unit, $request->file('receipt'), UnitEvidence::PAYMENT_RECEIPT, $policy, $payment);

            $policy->update(["{$payment}_payment_paid_at" => $request->validated('paid_at')]);

            if ($payment !== 'second') {
                return null;
            }

            // El periodo nuevo arranca cuando cierra el anterior, con dos
            // semestres de seis meses exactos. Si el día no existe en el mes
            // (31 de agosto → febrero) se queda en el último.
            $firstStart = $policy->second_payment_ends_on->copy();
            $firstEnd = $firstStart->copy()->addMonthsNoOverflow(UnitPolicy::SEMESTER_MONTHS);
            $secondEnd = $firstEnd->copy()->addMonthsNoOverflow(UnitPolicy::SEMESTER_MONTHS);

            return $unit->policies()->create([
                'policy' => $request->validated('new_policy'),
                'certificate' => $request->validated('new_certificate'),
                'first_payment_starts_on' => $firstStart,
                'first_payment_ends_on' => $firstEnd,
                'first_payment_amount' => $request->validated('new_first_payment_amount'),
                'second_payment_starts_on' => $firstEnd,
                'second_payment_ends_on' => $secondEnd,
                'second_payment_amount' => $request->validated('new_second_payment_amount'),
                'renewed_from_id' => $policy->id,
                'created_by' => $request->user()->id,
            ]);
        });

        $label = $payment === 'first' ? 'primer' : 'segundo';

        if (! $renewal) {
            return back()->with('success', "Se registró el {$label} pago de la póliza {$policy->policy}.");
        }

        return back()->with('success', "Se registró el {$label} pago y la unidad se renovó con la póliza {$renewal->policy}.");
    }

    /** DELETE /flotillas/{unit} */
    public function destroy(Request $request, Unit $unit): RedirectResponse
    {
        // Primero Outlook: después del delete ya no hay de dónde sacar los ids.
        $this->calendar->forget($unit, $request->user());

        // Los archivos no se van con la fila: el disco no sabe de llaves foráneas.
        foreach ($unit->evidences as $evidence) {
            Storage::disk('local')->delete($evidence->path);
        }

        // Periodos, evidencias y avisos se van en cascada con la unidad.
        $unit->delete();

        return to_route('fleets.index')->with('success', "Se eliminó la unidad {$unit->brand} {$unit->model}.");
    }

    /**
     * Borra los documentos que se quitaron en el formulario: primero el
     * archivo del disco y luego el registro, para no dejar basura colgando.
     *
     * El FormRequest ya comprobó que cada id es un documento de esta unidad.
     */
    private function forgetEvidences(UpdateUnitRequest $request, Unit $unit): void
    {
        $ids = $request->validated('remove_evidences') ?? [];

        foreach ($unit->evidences()->whereIn('id', $ids)->get() as $evidence) {
            Storage::disk('local')->delete($evidence->path);
            $evidence->delete();
        }
    }

    /** Los documentos oficiales que llegan en el formulario: son de la unidad, sin periodo. */
    private function storeDocuments(StoreUnitRequest|UpdateUnitRequest $request, Unit $unit): void
    {
        foreach ($request->file('evidences', []) as $file) {
            $this->storeEvidence($request, $unit, $file, UnitEvidence::OFFICIAL_DOCUMENT);
        }
    }

    /**
     * Guarda el archivo en disco y deja en la base su ruta y el nombre con
     * el que lo subieron, que es el que se va a descargar. Los comprobantes
     * llevan además su periodo y de cuál de los dos pagos son.
     */
    private function storeEvidence(Request $request, Unit $unit, UploadedFile $file, string $type, ?UnitPolicy $policy = null, ?string $payment = null): void
    {
        $unit->evidences()->create([
            'unit_policy_id' => $policy?->id,
            'type' => $type,
            'payment' => $payment,
            'path' => Storage::disk('local')->putFile("units/{$unit->id}", $file),
            'name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => $request->user()->id,
        ]);
    }

    /**
     * Lo que necesita la tabla, ya calculado. La unidad pone el vehículo; la
     * póliza vigente, la fecha límite del semestre más reciente y la del año
     * (la del segundo pago), cada una con cómo va: pagada, pendiente o
     * vencida. Sin póliza todo eso sale en null: queda por asignar.
     *
     * @return array<string, mixed>
     */
    private function summary(Unit $unit): array
    {
        $policy = $unit->currentPolicy;
        $semester = $policy?->currentSemester();

        return [
            'id' => $unit->id,
            'brand' => $unit->brand,
            'model' => $unit->model,
            'serial_number' => $unit->serial_number,
            'plate' => $unit->plate,
            'economic_number' => $unit->economic_number,
            'responsible' => $unit->responsible,
            'status' => $unit->status,
            'policy' => $policy?->policy,
            'semester' => $policy ? [
                'ends_on' => $policy->{"{$semester}_payment_ends_on"}?->toDateString(),
                'status' => $policy->paymentStatus($semester),
            ] : null,
            'annual' => $policy ? [
                'ends_on' => $policy->second_payment_ends_on?->toDateString(),
                'status' => $policy->annualStatus(),
            ] : null,
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
            // Por vencer: algún semestre sin pagar del periodo vigente cierra
            // dentro de la ventana. Cada pago se evalúa por su cuenta.
            'payments' => Unit::query()
                ->whereHas('currentPolicy', fn ($query) => $query->where(fn ($q) => $q
                    ->where(fn ($q2) => $q2->whereNull('first_payment_paid_at')->whereBetween('first_payment_ends_on', [$today, $limit]))
                    ->orWhere(fn ($q2) => $q2->whereNull('second_payment_paid_at')->whereBetween('second_payment_ends_on', [$today, $limit]))))
                ->count(),
            'maintenance' => Unit::query()->where('status', 'maintenance')->count(),
        ];
    }
}
