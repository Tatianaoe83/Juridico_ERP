<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fleet\StoreUnitRequest;
use App\Http\Requests\Fleet\UpdateUnitRequest;
use App\Models\BusinessUnit;
use App\Models\Unit;
use App\Models\UnitEvidence;
use App\Models\UnitPolicy;
use App\Services\UnitCalendar;
use App\Support\CoverageStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Flotillas (Cumplimiento): las unidades con su póliza vehicular. La unidad y
 * su póliza vigente se dan de alta y se editan juntas, en el mismo formulario.
 * Aquí no se registran pagos: solo la vigencia y el costo.
 */
class FleetController extends Controller
{
    private const PER_PAGE = 10;

    /**
     * Las pestañas de la tabla: automóviles con póliza empresarial, pólizas de
     * maquinaria empresariales y, aparte, las unidades con póliza del
     * proveedor (automóviles o máquinas), que se manejan distinto.
     */
    private const TABS = ['vehicles', 'machinery', 'supplier'];

    /** Las fechas de la póliza se agendan en Outlook, como las de obra. */
    public function __construct(private readonly UnitCalendar $calendar) {}

    public function index(Request $request): Response
    {
        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : self::TABS[0];

        $search = trim((string) $request->query('search', ''));

        // Un estado que no existe se ignora en vez de dejar la tabla vacía.
        $status = in_array($request->query('status'), Unit::STATUSES, true) ? $request->query('status') : null;

        // La marca se elige de lo ya capturado, así que se compara completa.
        $brand = trim((string) $request->query('brand', '')) ?: null;

        // La unidad de negocio viene del catálogo: se filtra por su id.
        $businessUnit = (int) $request->query('business_unit') ?: null;

        $rows = match ($tab) {
            'machinery' => $this->machineryPolicies($search, $status, $brand, $businessUnit),
            'supplier' => $this->supplierPolicies($search, $status, $brand, $businessUnit),
            default => $this->vehicles($search, $status, $brand, $businessUnit),
        };

        return Inertia::render('Fleets/Index', [
            'tab' => $tab,
            'rows' => [
                'data' => $rows->getCollection()->map(fn (Unit|UnitPolicy $row) => match (true) {
                    $tab === 'supplier' => $this->supplierSummary($row),
                    $row instanceof UnitPolicy => MachineryController::summary($row),
                    default => $this->summary($row),
                }),
                'meta' => [
                    'total' => $rows->total(),
                    'per_page' => $rows->perPage(),
                    'current_page' => $rows->currentPage(),
                    'last_page' => $rows->lastPage(),
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
     * Las unidades que llevan su propia póliza. Las máquinas de una póliza de
     * maquinaria y las de póliza del proveedor van en las otras pestañas.
     */
    private function vehicles(string $search, ?string $status, ?string $brand, ?int $businessUnit): LengthAwarePaginator
    {
        return Unit::with('currentPolicy')
            ->whereNull('unit_policy_id')
            ->whereDoesntHave('currentPolicy', fn ($query) => $query->where('source', UnitPolicy::SUPPLIER))
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
    }

    /**
     * Las pólizas de maquinaria, una fila cada una. La unidad de negocio es de
     * la póliza; el estado y la marca se aplican a sus máquinas: sale la
     * póliza si alguna coincide.
     */
    private function machineryPolicies(string $search, ?string $status, ?string $brand, ?int $businessUnit): LengthAwarePaginator
    {
        return UnitPolicy::with(['machines', 'businessUnit', 'insurer'])
            ->where('kind', UnitPolicy::MACHINERY)
            ->where('source', UnitPolicy::CORPORATE)
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('policy', 'like', "%{$search}%")
                ->orWhereHas('insurer', fn ($q2) => $q2->where('name', 'like', "%{$search}%"))
                ->orWhereHas('businessUnit', fn ($q2) => $q2->where('name', 'like', "%{$search}%"))
                ->orWhereHas('machines', fn ($q2) => $q2->where(fn ($q3) => $q3
                    ->where('machine_type', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%")
                    ->orWhere('model', 'like', "%{$search}%")
                    ->orWhere('economic_number', 'like', "%{$search}%")))))
            ->when($status, fn ($query) => $query->whereHas('machines', fn ($q) => $q->where('status', $status)))
            ->when($brand, fn ($query) => $query->whereHas('machines', fn ($q) => $q->where('brand', $brand)))
            ->when($businessUnit, fn ($query) => $query->where('business_unit_id', $businessUnit))
            ->orderBy('second_payment_ends_on')
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    /**
     * Las pólizas preferentes (del proveedor), una por fila: la vigente de
     * cada automóvil y cada póliza de maquinaria con todas sus máquinas. Los
     * filtros de la unidad (estado, marca) se aplican al automóvil o a
     * cualquiera de las máquinas; la unidad de negocio, a la del automóvil o
     * a la de la póliza de maquinaria.
     */
    private function supplierPolicies(string $search, ?string $status, ?string $brand, ?int $businessUnit): LengthAwarePaginator
    {
        // El automóvil o alguna de las máquinas.
        $covered = fn ($query, $match) => $query->where(fn ($q) => $q
            ->where(fn ($q2) => $q2->where('kind', UnitPolicy::VEHICLE)->whereHas('unit', $match))
            ->orWhere(fn ($q2) => $q2->where('kind', UnitPolicy::MACHINERY)->whereHas('machines', $match)));

        return UnitPolicy::with(['unit.businessUnit', 'machines', 'businessUnit', 'supplier'])
            ->where('source', UnitPolicy::SUPPLIER)
            ->where(fn ($query) => $query
                ->where('kind', UnitPolicy::MACHINERY)
                // Del automóvil, solo la vigente: la más reciente de su unidad,
                // igual que Unit::currentPolicy().
                ->orWhere(fn ($q) => $q
                    ->where('kind', UnitPolicy::VEHICLE)
                    ->whereNotExists(fn ($newer) => $newer
                        ->from('unit_policies as newer')
                        ->whereColumn('newer.unit_id', 'unit_policies.unit_id')
                        ->where(fn ($w) => $w
                            ->whereColumn('newer.first_payment_starts_on', '>', 'unit_policies.first_payment_starts_on')
                            ->orWhere(fn ($w2) => $w2
                                ->whereColumn('newer.first_payment_starts_on', 'unit_policies.first_payment_starts_on')
                                ->whereColumn('newer.id', '>', 'unit_policies.id'))))))
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('policy', 'like', "%{$search}%")
                ->orWhereHas('supplier', fn ($q2) => $q2->where('name', 'like', "%{$search}%"))
                ->orWhereHas('businessUnit', fn ($q2) => $q2->where('name', 'like', "%{$search}%"))
                ->orWhere(fn ($q2) => $covered($q2, fn ($unit) => $unit->where(fn ($u) => $u
                    ->where('machine_type', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%")
                    ->orWhere('model', 'like', "%{$search}%")
                    ->orWhere('plate', 'like', "%{$search}%")
                    ->orWhere('economic_number', 'like', "%{$search}%")
                    ->orWhereHas('businessUnit', fn ($b) => $b->where('name', 'like', "%{$search}%")))))))
            ->when($status, fn ($query) => $covered($query, fn ($unit) => $unit->where('status', $status)))
            ->when($brand, fn ($query) => $covered($query, fn ($unit) => $unit->where('brand', $brand)))
            ->when($businessUnit, fn ($query) => $query->where(fn ($q) => $q
                ->where(fn ($q2) => $q2->where('kind', UnitPolicy::VEHICLE)->whereHas('unit', fn ($unit) => $unit->where('business_unit_id', $businessUnit)))
                ->orWhere(fn ($q2) => $q2->where('kind', UnitPolicy::MACHINERY)->where('business_unit_id', $businessUnit))))
            ->orderBy('second_payment_ends_on')
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    /**
     * La fila de la pestaña de preferentes: la póliza, a favor de quién queda
     * (el beneficiario preferente) y lo que cubre. El automóvil se ve y se
     * edita desde su unidad; la maquinaria, desde su póliza, con sus máquinas
     * en la misma fila.
     *
     * @return array<string, mixed>
     */
    private function supplierSummary(UnitPolicy $policy): array
    {
        $machinery = $policy->isMachinery();
        $unit = $policy->unit;
        $statuses = $machinery ? $policy->machines->pluck('status')->unique() : collect([$unit?->status]);

        return [
            'id' => $policy->id,
            'kind' => $machinery ? UnitPolicy::MACHINERY : UnitPolicy::VEHICLE,
            'unit_id' => $unit?->id,
            'brand' => $unit?->brand,
            'model' => $unit?->model,
            'plate' => $unit?->plate,
            'economic_number' => $unit?->economic_number,
            'business_unit' => $policy->businessUnitName(),
            // Si las máquinas no van todas igual, no hay un solo estado que mostrar.
            'status' => $statuses->count() === 1 ? $statuses->first() : null,
            'policy' => $policy->policy,
            'supplier' => $policy->supplier?->name,
            'valid_until' => $policy->second_payment_ends_on?->toDateString(),
            'coverage_status' => $policy->coverageStatus(),
            'reminders_enabled' => $policy->reminders_enabled,
            'machines' => $machinery ? $policy->machines->map(fn (Unit $machine) => [
                'id' => $machine->id,
                'machine_type' => $machine->machine_type,
                'brand' => $machine->brand,
                'model' => $machine->model,
                'economic_number' => $machine->economic_number,
                'status' => $machine->status,
            ]) : [],
        ];
    }

    /**
     * PATCH /flotillas/polizas/{policy}/recordatorios
     *
     * Prende o apaga los recordatorios por correo de la póliza, de cualquiera
     * de las cuatro: automóvil, maquinaria y sus preferentes. En el automóvil
     * va en su periodo vigente.
     */
    public function reminders(UnitPolicy $policy): RedirectResponse
    {
        $policy->update(['reminders_enabled' => ! $policy->reminders_enabled]);

        return back()->with('success', $policy->reminders_enabled
            ? "Se activaron los recordatorios por correo de la póliza {$policy->policy}."
            : "Se desactivaron los recordatorios por correo de la póliza {$policy->policy}.");
    }

    /**
     * GET /flotillas/crear
     *
     * El alta va en su propia vista y no en un modal: son una decena de
     * campos y archivos.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('Fleets/Create', [
            // Con qué tipo abre: el de la pestaña de la tabla, si venía.
            'type' => in_array($request->query('tipo'), Unit::TYPES, true) ? $request->query('tipo') : null,
            // Desde la pestaña del proveedor, la póliza ya abre como del proveedor.
            'source' => $request->query('origen') === UnitPolicy::SUPPLIER ? UnitPolicy::SUPPLIER : UnitPolicy::CORPORATE,
            'types' => Unit::TYPES,
            'coverages' => UnitPolicy::COVERAGES[UnitPolicy::VEHICLE],
            // Lo de la póliza de maquinaria; incluye unidades de negocio y estados.
            ...MachineryController::formOptions(),
        ]);
    }

    /** POST /flotillas: la unidad nace con su póliza. */
    public function store(StoreUnitRequest $request): RedirectResponse
    {
        [$unit, $policy] = DB::transaction(function () use ($request) {
            $unit = Unit::create([
                ...Arr::except($request->validated(), ['evidences', ...StoreUnitRequest::POLICY_FIELDS]),
                'created_by' => $request->user()->id,
            ]);

            $policy = $unit->policies()->create([
                ...$this->policyAttributes($request),
                'kind' => UnitPolicy::VEHICLE,
                'created_by' => $request->user()->id,
            ]);

            $this->storeDocuments($request, $unit);

            return [$unit, $policy];
        });

        $agendada = $this->calendar->sync($policy, $request->user());

        return to_route('fleets.index', $this->tabOf($policy))
            ->with('success', "Se registró la unidad {$unit->brand} {$unit->model}.".$this->calendarNote($agendada));
    }

    /**
     * GET /flotillas/{unit}
     *
     * El detalle del vehículo, su póliza vigente y sus documentos oficiales.
     */
    public function show(Unit $unit): Response|RedirectResponse
    {
        // Una máquina se ve en su póliza.
        if ($unit->unit_policy_id) {
            return to_route('fleets.machinery.show', $unit->unit_policy_id);
        }

        $unit->load(['businessUnit', 'currentPolicy']);

        $documents = $unit->evidences()
            ->where('type', UnitEvidence::OFFICIAL_DOCUMENT)
            ->with('uploader:id,name')
            ->orderBy('id')
            ->get();

        $policy = $unit->currentPolicy;

        return Inertia::render('Fleets/Show', [
            'unit' => [
                ...$this->summary($unit),
                'business_unit' => $unit->businessUnit?->name,
                'comments' => $unit->comments,
                'policy_detail' => $policy ? [
                    'policy' => $policy->policy,
                    'certificate' => $policy->certificate,
                    'insurer' => $policy->insurer?->name,
                    'coverage' => $policy->coverage,
                    'endorsement' => (bool) $policy->endorsement,
                    'financing' => (bool) $policy->financing,
                    'status' => $policy->coverageStatus(),
                    ...self::sourceDetail($policy),
                    // La del proveedor no tiene semestres: su pago único va aparte.
                    'payment' => $policy->isFromSupplier() ? [
                        'amount' => $policy->first_payment_amount !== null ? (float) $policy->first_payment_amount : null,
                    ] : null,
                    'semesters' => $policy->isFromSupplier() ? [] : collect(UnitPolicy::PAYMENTS)->map(fn (string $payment) => [
                        'payment' => $payment,
                        'starts_on' => $policy->{"{$payment}_payment_starts_on"}?->toDateString(),
                        'ends_on' => $policy->{"{$payment}_payment_ends_on"}?->toDateString(),
                        'amount' => $policy->{"{$payment}_payment_amount"} !== null ? (float) $policy->{"{$payment}_payment_amount"} : null,
                    ]),
                    'annual_cost' => $policy->annualCost(),
                    'tax' => $policy->tax(),
                    'subtotal' => $policy->subtotal(),
                    'tax_rate' => UnitPolicy::TAX_RATE,
                ] : null,
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
     * El vehículo y su póliza vigente, con los valores tal como se capturan.
     */
    public function edit(Unit $unit): Response|RedirectResponse
    {
        if ($unit->unit_policy_id) {
            return to_route('fleets.machinery.edit', $unit->unit_policy_id);
        }

        $policy = $unit->currentPolicy;

        return Inertia::render('Fleets/Edit', [
            'unit' => [
                'id' => $unit->id,
                'business_unit_id' => $unit->business_unit_id,
                'type' => $unit->type,
                'brand' => $unit->brand,
                'model' => $unit->model,
                'serial_number' => $unit->serial_number,
                'plate' => $unit->plate,
                'economic_number' => $unit->economic_number,
                'responsible' => $unit->responsible,
                'status' => $unit->status,
                'comments' => $unit->comments,
                // La póliza vigente. Las capturadas antes pueden no tenerla:
                // al guardar se crea.
                'policy' => $policy?->policy,
                'certificate' => $policy?->certificate,
                'insurer_id' => $policy?->insurer_id,
                'coverage' => $policy?->coverage,
                'endorsement' => (bool) $policy?->endorsement,
                'financing' => (bool) $policy?->financing,
                'valid_from' => $policy?->first_payment_starts_on?->toDateString(),
                'valid_until' => $policy?->second_payment_ends_on?->toDateString(),
                'source' => $policy?->source ?? UnitPolicy::CORPORATE,
                'supplier_id' => $policy?->supplier_id,
                'first_payment_amount' => $policy?->first_payment_amount,
                'second_payment_amount' => $policy?->second_payment_amount,
                // Sus documentos oficiales, para poder quitarlos. Los
                // comprobantes de pago no se tocan desde aquí.
                'evidences' => $unit->evidences()
                    ->where('type', UnitEvidence::OFFICIAL_DOCUMENT)
                    ->orderBy('id')
                    ->get(['id', 'name', 'size']),
            ],
            ...CatalogController::options(),
            'statuses' => Unit::STATUSES,
            'coverages' => UnitPolicy::COVERAGES[UnitPolicy::VEHICLE],
        ]);
    }

    /** PATCH /flotillas/{unit}: el vehículo y su póliza vigente. */
    public function update(UpdateUnitRequest $request, Unit $unit): RedirectResponse
    {
        $policy = DB::transaction(function () use ($request, $unit) {
            $unit->update(Arr::except($request->validated(), ['evidences', 'remove_evidences', ...UpdateUnitRequest::POLICY_FIELDS]));

            // Si la unidad se capturó antes sin póliza, aquí nace la suya.
            $policy = $unit->currentPolicy ?? $unit->policies()->make([
                'kind' => UnitPolicy::VEHICLE,
                'created_by' => $request->user()->id,
            ]);

            $policy->fill($this->policyAttributes($request))->save();

            $this->forgetEvidences($request, $unit);
            $this->storeDocuments($request, $unit);

            return $policy;
        });

        $note = UnitCalendar::needsSync($policy) ? $this->calendarNote($this->calendar->sync($policy, $request->user())) : '';

        return to_route('fleets.index', $this->tabOf($policy))->with('success', "Se actualizó la unidad {$unit->brand} {$unit->model}.".$note);
    }

    /**
     * Lo capturado de la póliza, con los dos semestres calculados desde el
     * inicio de la vigencia.
     *
     * @return array<string, mixed>
     */
    private function policyAttributes(StoreUnitRequest|UpdateUnitRequest $request): array
    {
        $policy = [
            ...Arr::except($request->safe()->only(StoreUnitRequest::POLICY_FIELDS), ['valid_from', 'valid_until']),
            ...$request->policySource(),
            'endorsement' => $request->boolean('endorsement'),
            'financing' => $request->boolean('financing'),
            // La vehicular no lleva fechas límite de pago: las que hubiera se
            // limpian y sus eventos se quitan de Outlook al sincronizar.
            'first_payment_due_on' => null,
            'second_payment_due_on' => null,
        ];

        // La del proveedor: su vigencia tal cual, sin semestres, y un pago
        // único anual en las columnas del primero. Se guarda en las columnas de
        // siempre para que el estado y Outlook la lean igual.
        if ($request->fromSupplier()) {
            return [
                ...$policy,
                'first_payment_starts_on' => $request->validated('valid_from'),
                'first_payment_ends_on' => null,
                'second_payment_starts_on' => null,
                'second_payment_ends_on' => $request->validated('valid_until'),
                'second_payment_amount' => null,
            ];
        }

        return [
            ...$policy,
            ...UnitPolicy::semestersFrom(Carbon::parse($request->validated('valid_from'))),
        ];
    }

    /**
     * Agendar es un extra: si Outlook no respondió o la cuenta no está
     * vinculada, se dice en el mismo aviso en vez de fallar el guardado.
     */
    private function calendarNote(bool $agendada): string
    {
        return $agendada
            ? ' Se agendó en el calendario.'
            : ' No se pudo agendar en el calendario: revisa la conexión con Outlook.';
    }

    /** DELETE /flotillas/{unit} */
    public function destroy(Request $request, Unit $unit): RedirectResponse
    {
        // La máquina se quita desde su póliza de maquinaria: borrarla por aquí
        // dejaría la póliza sin ella y sin tocar sus eventos.
        abort_if($unit->unit_policy_id !== null, 404);

        // A qué pestaña se vuelve: se lee antes de que la póliza se vaya.
        $tab = $this->tabOf($unit->currentPolicy);

        // Primero Outlook: después del delete ya no hay de dónde sacar los ids.
        $this->calendar->forget($unit, $request->user());

        // Los archivos no se van con la fila: el disco no sabe de llaves foráneas.
        foreach ($unit->evidences as $evidence) {
            Storage::disk('local')->delete($evidence->path);
        }

        // Periodos, evidencias y avisos se van en cascada con la unidad.
        $unit->delete();

        return to_route('fleets.index', $tab)->with('success', "Se eliminó la unidad {$unit->brand} {$unit->model}.");
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
            $this->storeEvidence($request, $unit, $file);
        }
    }

    /**
     * Guarda el archivo en disco y deja en la base su ruta y el nombre con
     * el que lo subieron, que es el que se va a descargar. Los comprobantes
     * de pago no pasan por aquí: se suben desde Pólizas.
     */
    private function storeEvidence(Request $request, Unit $unit, UploadedFile $file): void
    {
        $unit->evidences()->create([
            'type' => UnitEvidence::OFFICIAL_DOCUMENT,
            'path' => Storage::disk('local')->putFile("units/{$unit->id}", $file),
            'name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => $request->user()->id,
        ]);
    }

    /**
     * Lo que necesita la tabla, ya calculado. La unidad pone el vehículo; la
     * póliza vigente, el cierre del semestre en curso y el fin de la vigencia
     * anual, cada uno con cómo va: vigente, por vencer o vencida. Sin póliza
     * todo eso sale en null: queda por asignar.
     *
     * @return array<string, mixed>
     */
    private function summary(Unit $unit): array
    {
        $policy = $unit->currentPolicy;
        $semester = $policy?->currentSemester();
        $semesterEnd = $policy?->{"{$semester}_payment_ends_on"};

        return [
            'id' => $unit->id,
            'type' => $unit->type,
            'brand' => $unit->brand,
            'model' => $unit->model,
            'serial_number' => $unit->serial_number,
            'plate' => $unit->plate,
            'economic_number' => $unit->economic_number,
            'responsible' => $unit->responsible,
            'status' => $unit->status,
            'policy' => $policy?->policy,
            'policy_id' => $policy?->id,
            'policy_source' => $policy?->source,
            'reminders_enabled' => (bool) $policy?->reminders_enabled,
            // La del proveedor no tiene semestres: solo cuenta su vigencia.
            'semester' => $policy && ! $policy->isFromSupplier() ? [
                'ends_on' => $semesterEnd?->toDateString(),
                'status' => CoverageStatus::of($semesterEnd, $policy->cancelled_on),
            ] : null,
            'annual' => $policy ? [
                'ends_on' => $policy->second_payment_ends_on?->toDateString(),
                'status' => $policy->coverageStatus(),
            ] : null,
        ];
    }

    /**
     * La pestaña a la que se vuelve después de guardar o borrar: la del
     * proveedor si de ahí es la póliza; si no, la de siempre.
     *
     * @return array<string, string>
     */
    public static function tabOf(?UnitPolicy $policy): array
    {
        return match (true) {
            (bool) $policy?->isFromSupplier() => ['tab' => 'supplier'],
            (bool) $policy?->isMachinery() => ['tab' => 'machinery'],
            default => [],
        };
    }

    /**
     * El origen de la póliza y, si es del proveedor, quién es el beneficiario
     * preferente. Lo usa también la de maquinaria.
     *
     * @return array<string, mixed>
     */
    public static function sourceDetail(UnitPolicy $policy): array
    {
        return [
            'source' => $policy->source ?? UnitPolicy::CORPORATE,
            'supplier' => $policy->supplier?->name,
            'supplier_id' => $policy->supplier_id,
            'valid_from' => $policy->first_payment_starts_on?->toDateString(),
            'valid_until' => $policy->second_payment_ends_on?->toDateString(),
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
            // Por vencer: algún semestre de la póliza vigente cierra dentro de
            // la ventana; en las máquinas, el fin de vigencia de su póliza. Las
            // canceladas no cuentan.
            'expiring' => Unit::query()
                ->where(fn ($query) => $query
                    ->whereHas('currentPolicy', fn ($q) => $q
                        ->whereNull('cancelled_on')
                        ->where(fn ($q2) => $q2
                            ->whereBetween('first_payment_ends_on', [$today, $limit])
                            ->orWhereBetween('second_payment_ends_on', [$today, $limit])))
                    ->orWhereHas('machineryPolicy', fn ($q) => $q
                        ->whereNull('cancelled_on')
                        ->whereBetween('second_payment_ends_on', [$today, $limit])))
                ->count(),
            'maintenance' => Unit::query()->where('status', 'maintenance')->count(),
        ];
    }
}
