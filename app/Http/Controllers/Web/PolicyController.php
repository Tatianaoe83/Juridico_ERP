<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Policy\SavePolicyRequest;
use App\Models\Bond;
use App\Models\BusinessUnit;
use App\Models\ConstructionPolicy;
use App\Models\ConstructionPolicyDocument;
use App\Models\ConstructionPolicyEndorsement;
use App\Services\ConstructionPolicyCalendar;
use App\Support\CoverageStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Pólizas y Fianzas (Cumplimiento): una sola tabla con las dos coberturas.
 * Cada renglón dice qué es, quién la da, sobre qué, su vigencia, la suma y
 * cómo va. Las pólizas de aquí son solo de obra: las vehiculares y las de
 * maquinaria viven en Flotillas.
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

    public function __construct(private readonly ConstructionPolicyCalendar $calendar) {}

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
     * GET /polizas/{policy}
     *
     * La póliza, la obra asegurada, el monto desglosado, sus documentos y sus endosos.
     */
    public function show(ConstructionPolicy $policy): Response
    {
        $policy->load([
            'businessUnit:id,name',
            'creator:id,name',
            'documents.uploader:id,name',
            'endorsements.creator:id,name',
        ]);

        return Inertia::render('Policies/Show', [
            'policy' => [
                'id' => $policy->id,
                'policy' => $policy->policy,
                'insurer' => $policy->insurer,
                'coverage' => $policy->coverage,
                'status' => $policy->status(),
                'project' => $policy->project,
                'project_address' => $policy->project_address,
                'business_unit' => $policy->businessUnit?->name,
                'valid_from' => $policy->valid_from?->toDateString(),
                'valid_until' => $policy->valid_until?->toDateString(),
                'amount' => $policy->amount !== null ? (float) $policy->amount : null,
                'endorsements_amount' => (float) $policy->endorsements->sum('amount'),
                'total' => $policy->total(),
                'tax' => $policy->tax(),
                'subtotal' => $policy->subtotal(),
                'tax_rate' => ConstructionPolicy::TAX_RATE,
                'documents' => $policy->documents->map(fn (ConstructionPolicyDocument $document) => [
                    'id' => $document->id,
                    'name' => $document->name,
                    'size' => $document->size,
                    'uploaded_by' => $document->uploader?->name,
                ]),
                'endorsements' => $policy->endorsements->map(fn (ConstructionPolicyEndorsement $endorsement) => [
                    'id' => $endorsement->id,
                    'endorsement' => $endorsement->endorsement,
                    'previous_valid_until' => $endorsement->previous_valid_until?->toDateString(),
                    'valid_until' => $endorsement->valid_until?->toDateString(),
                    'amount' => $endorsement->amount !== null ? (float) $endorsement->amount : null,
                    'file' => $endorsement->path ? ['name' => $endorsement->name, 'size' => $endorsement->size] : null,
                    'created_by' => $endorsement->creator?->name,
                    'created_at' => $endorsement->created_at?->toIso8601String(),
                ]),
                'comments' => $policy->comments,
                'created_by' => $policy->creator?->name,
                'created_at' => $policy->created_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * GET /polizas/crear
     *
     * El alta de pólizas y de fianzas: la misma página, se elige arriba qué se
     * registra. `?tipo=fianza` la abre ya en fianza.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('Policies/Create', [
            ...$this->formOptions(),
            'type' => $request->query('tipo') === 'fianza' ? 'bond' : 'policy',
        ]);
    }

    /** POST /polizas */
    public function store(SavePolicyRequest $request): RedirectResponse
    {
        $policy = DB::transaction(function () use ($request) {
            $policy = ConstructionPolicy::create([
                ...$request->policyAttributes(),
                'created_by' => $request->user()->id,
            ]);

            $this->storeDocuments($request, $policy);

            return $policy;
        });

        $agendada = $this->calendar->sync($policy, $request->user());

        return to_route('policies.show', $policy)
            ->with('success', "Se registró la póliza {$policy->policy}.".$this->calendarNote($agendada));
    }

    /** GET /polizas/{policy}/editar */
    public function edit(ConstructionPolicy $policy): Response
    {
        return Inertia::render('Policies/Edit', [
            ...$this->formOptions(),
            'type' => 'policy',
            'policy' => [
                'id' => $policy->id,
                'project' => $policy->project,
                'project_address' => $policy->project_address,
                'business_unit_id' => $policy->business_unit_id,
                'policy' => $policy->policy,
                'insurer' => $policy->insurer,
                'coverage' => $policy->coverage,
                'valid_from' => $policy->valid_from?->toDateString(),
                'valid_until' => $policy->valid_until?->toDateString(),
                // Con endosos el fin solo se ve: lo mueve el último endoso.
                'endorsed' => $policy->endorsements()->exists(),
                'amount' => $policy->amount,
                'comments' => $policy->comments,
                // Sus documentos, para poder quitarlos.
                'documents' => $policy->documents()->get(['id', 'name', 'size']),
            ],
        ]);
    }

    /** PATCH /polizas/{policy} (llega como POST con _method: lleva archivos) */
    public function update(SavePolicyRequest $request, ConstructionPolicy $policy): RedirectResponse
    {
        DB::transaction(function () use ($request, $policy) {
            $policy->update($request->policyAttributes());

            $this->forgetDocuments($request, $policy);
            $this->storeDocuments($request, $policy);
        });

        // Outlook solo se toca si se movió el fin o aún no tiene evento:
        // actualizarlo le vuelve a llegar el aviso a los compartidos.
        $note = $policy->wasChanged('valid_until') || ! $policy->calendar_event_id
            ? $this->calendarNote($this->calendar->sync($policy, $request->user()))
            : '';

        return to_route('policies.show', $policy)->with('success', "Se actualizó la póliza {$policy->policy}.".$note);
    }

    /**
     * DELETE /polizas/{policy}
     *
     * Documentos y endosos se van en cascada con la póliza, pero el disco no
     * sabe de llaves foráneas: sus archivos se borran primero.
     */
    public function destroy(Request $request, ConstructionPolicy $policy): RedirectResponse
    {
        // Primero el evento: después del delete ya no hay de dónde sacar su id.
        $this->calendar->forget($policy, $request->user());

        DB::transaction(function () use ($policy) {
            $paths = $policy->documents->pluck('path')->concat($policy->endorsements->pluck('path'))->filter();

            Storage::disk('local')->delete($paths->all());

            $policy->delete();
        });

        return to_route('policies.index')->with('success', "Se eliminó la póliza {$policy->policy}.");
    }

    /**
     * GET /polizas/{policy}/documentos/{document}
     *
     * Los documentos viven fuera de public: se sirven por aquí, con el nombre
     * con el que se subieron y solo si son de esta póliza.
     */
    public function document(ConstructionPolicy $policy, ConstructionPolicyDocument $document): StreamedResponse
    {
        abort_unless($document->construction_policy_id === $policy->id, 404);
        abort_unless(Storage::disk('local')->exists($document->path), 404);

        return Storage::disk('local')->download($document->path, $document->name);
    }

    /** Guarda en disco los documentos que llegan en el formulario. */
    private function storeDocuments(SavePolicyRequest $request, ConstructionPolicy $policy): void
    {
        foreach ($request->file('documents', []) as $file) {
            $policy->documents()->create([
                'path' => Storage::disk('local')->putFile("policies/{$policy->id}", $file),
                'name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
                'uploaded_by' => $request->user()->id,
            ]);
        }
    }

    /**
     * Borra los documentos que se quitaron en el formulario: primero el
     * archivo del disco y luego el registro. El FormRequest ya comprobó que
     * cada id es de esta póliza.
     */
    private function forgetDocuments(SavePolicyRequest $request, ConstructionPolicy $policy): void
    {
        foreach ($policy->documents()->whereIn('id', $request->validated('remove_documents') ?? [])->get() as $document) {
            Storage::disk('local')->delete($document->path);
            $document->delete();
        }
    }

    /**
     * Agendar es un extra: si Outlook no respondió o la cuenta no está
     * vinculada, se dice en el mismo aviso en vez de fallar el guardado.
     */
    public static function calendarNote(bool $agendada): string
    {
        return $agendada
            ? ' Se agendó en el calendario.'
            : ' No se pudo agendar en el calendario: revisa la conexión con Outlook.';
    }

    /**
     * Lo que necesita el formulario: las coberturas de obra, las unidades de
     * negocio (para la póliza y la fianza) y las categorías de fianza.
     *
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'businessUnits' => BusinessUnit::orderBy('name')->get(['id', 'name']),
            'coverages' => ConstructionPolicy::COVERAGES,
            'categories' => Bond::CATEGORIES,
        ];
    }

    /**
     * Las pólizas de obra. Su fin de vigencia ya es el del último endoso.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function policies(): Collection
    {
        return ConstructionPolicy::with('businessUnit:id,name')
            ->withSum('endorsements', 'amount')
            ->get()
            ->map(fn (ConstructionPolicy $policy) => [
                'key' => "policy-{$policy->id}",
                'type' => 'policy',
                'id' => $policy->id,
                'number' => $policy->policy,
                'provider' => $policy->insurer,
                'coverage' => $policy->coverage,
                // Sobre qué: la obra con su dirección.
                'subject' => $policy->project,
                'detail' => $policy->project_address,
                'business_unit' => $policy->businessUnit?->name,
                'valid_from' => $policy->valid_from?->toDateString(),
                'valid_until' => $policy->valid_until?->toDateString(),
                // Lo que cuesta con todo y endosos; sin ningún monto capturado, nada.
                'amount' => $policy->amount === null && ! $policy->endorsements_sum_amount ? null : $policy->total(),
                'status' => $policy->status(),
                'search' => [$policy->policy, $policy->insurer, $policy->project, $policy->project_address, $policy->businessUnit?->name],
            ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function bonds(): Collection
    {
        return Bond::with('businessUnit:id,name')
            ->get()
            ->map(fn (Bond $bond) => [
                'key' => "bond-{$bond->id}",
                'type' => 'bond',
                'id' => $bond->id,
                'number' => $bond->bond,
                'provider' => $bond->bonding_company,
                'coverage' => null,
                'category' => $bond->category,
                // Sobre qué: a quién se garantiza y el asunto.
                'subject' => $bond->beneficiary,
                'detail' => $bond->related,
                'business_unit' => $bond->businessUnit?->name,
                'valid_from' => $bond->valid_from?->toDateString(),
                'valid_until' => $bond->valid_until?->toDateString(),
                'amount' => (float) $bond->amount,
                'status' => $bond->status(),
                'search' => [$bond->bond, $bond->bonding_company, $bond->beneficiary, $bond->related, $bond->source_document, $bond->businessUnit?->name],
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
            'total' => $all->count(),
            'active' => $count(CoverageStatus::ACTIVE),
            'expiring' => $count(CoverageStatus::EXPIRING),
            'expired' => $count(CoverageStatus::EXPIRED),
            'warning_days' => CoverageStatus::WARNING_DAYS,
        ];
    }
}
