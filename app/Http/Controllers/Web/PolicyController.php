<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Policy\RegisterPolicyPaymentRequest;
use App\Http\Requests\Policy\SavePolicyRequest;
use App\Models\Bond;
use App\Models\Unit;
use App\Models\UnitEvidence;
use App\Models\UnitPolicy;
use App\Support\CoverageStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
     * GET /polizas/{policy}
     *
     * Todo el periodo: la póliza, la unidad asegurada, las dos cuotas con sus
     * comprobantes, el costo desglosado y la cancelación.
     */
    public function show(UnitPolicy $policy): Response
    {
        $policy->load([
            'unit.businessUnit:id,name',
            'creator:id,name',
            'evidences' => fn ($query) => $query->where('type', UnitEvidence::PAYMENT_RECEIPT)->with('uploader:id,name')->orderBy('id'),
        ]);

        $unit = $policy->unit;
        $cancelled = $policy->coverageStatus() === CoverageStatus::CANCELLED;

        return Inertia::render('Policies/Show', [
            'policy' => [
                'id' => $policy->id,
                'policy' => $policy->policy,
                'certificate' => $policy->certificate,
                'insurer' => $policy->insurer,
                'coverage' => $policy->coverage,
                'endorsement' => (bool) $policy->endorsement,
                'status' => $policy->coverageStatus(),
                'valid_from' => $policy->first_payment_starts_on?->toDateString(),
                'valid_until' => $policy->second_payment_ends_on?->toDateString(),
                'annual_cost' => $policy->annualCost(),
                'tax' => $policy->tax(),
                'subtotal' => $policy->subtotal(),
                'tax_rate' => UnitPolicy::TAX_RATE,
                'payments' => collect(UnitPolicy::PAYMENTS)->map(fn (string $payment) => [
                    'payment' => $payment,
                    'starts_on' => $policy->{"{$payment}_payment_starts_on"}?->toDateString(),
                    'ends_on' => $policy->{"{$payment}_payment_ends_on"}?->toDateString(),
                    'due_on' => $policy->{"{$payment}_payment_due_on"}?->toDateString(),
                    'amount' => $policy->{"{$payment}_payment_amount"} !== null ? (float) $policy->{"{$payment}_payment_amount"} : null,
                    'paid_at' => $policy->{"{$payment}_payment_paid_at"}?->toDateString(),
                    'status' => $this->paymentStatus($policy, $payment, $cancelled),
                    'receipts' => $this->receiptsOf($policy, $payment),
                ]),
                'cancellation_requested_on' => $policy->cancellation_requested_on?->toDateString(),
                'cancelled_on' => $policy->cancelled_on?->toDateString(),
                'comments' => $policy->comments,
                'created_by' => $policy->creator?->name,
                'created_at' => $policy->created_at?->toIso8601String(),
                'unit' => $unit ? [
                    'id' => $unit->id,
                    'type' => $unit->type,
                    'brand' => $unit->brand,
                    'model' => $unit->model,
                    'serial_number' => $unit->serial_number,
                    'plate' => $unit->plate,
                    'economic_number' => $unit->economic_number,
                    'responsible' => $unit->responsible,
                    'business_unit' => $unit->businessUnit?->name,
                    'status' => $unit->status,
                ] : null,
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
            // Desde la ficha de una unidad puede llegar ya elegida.
            'unitId' => $request->integer('unidad') ?: null,
        ]);
    }

    /** POST /polizas */
    public function store(SavePolicyRequest $request): RedirectResponse
    {
        $policy = UnitPolicy::create([
            ...$this->attributes($request),
            'unit_id' => $request->validated('unit_id'),
            'created_by' => $request->user()->id,
        ]);

        return to_route('policies.show', $policy)->with('success', "Se registró la póliza {$policy->policy}.");
    }

    /** GET /polizas/{policy}/editar */
    public function edit(UnitPolicy $policy): Response
    {
        return Inertia::render('Policies/Edit', [
            ...$this->formOptions($policy->unit_id),
            'type' => 'policy',
            'policy' => [
                'id' => $policy->id,
                'unit_id' => $policy->unit_id,
                'policy' => $policy->policy,
                'certificate' => $policy->certificate,
                'insurer' => $policy->insurer,
                'coverage' => $policy->coverage,
                'endorsement' => (bool) $policy->endorsement,
                'valid_from' => $policy->first_payment_starts_on?->toDateString(),
                'first_payment_amount' => $policy->first_payment_amount,
                'first_payment_due_on' => $policy->first_payment_due_on?->toDateString(),
                'second_payment_amount' => $policy->second_payment_amount,
                'second_payment_due_on' => $policy->second_payment_due_on?->toDateString(),
                'cancellation_requested_on' => $policy->cancellation_requested_on?->toDateString(),
                'cancelled_on' => $policy->cancelled_on?->toDateString(),
                'comments' => $policy->comments,
            ],
        ]);
    }

    /** PATCH /polizas/{policy} */
    public function update(SavePolicyRequest $request, UnitPolicy $policy): RedirectResponse
    {
        $policy->update($this->attributes($request));

        return to_route('policies.show', $policy)->with('success', "Se actualizó la póliza {$policy->policy}.");
    }

    /**
     * DELETE /polizas/{policy}
     *
     * Los comprobantes se van en cascada con la póliza, pero el disco no sabe
     * de llaves foráneas: los archivos se borran primero.
     */
    public function destroy(UnitPolicy $policy): RedirectResponse
    {
        DB::transaction(function () use ($policy) {
            foreach ($policy->evidences as $evidence) {
                Storage::disk('local')->delete($evidence->path);
            }

            $policy->delete();
        });

        return to_route('policies.index')->with('success', "Se eliminó la póliza {$policy->policy}.");
    }

    /**
     * Lo capturado, con los dos semestres calculados desde el inicio de la
     * vigencia: seis meses exactos cada uno. Si el día no existe en el mes
     * (31 de agosto → febrero) se queda en el último.
     *
     * @return array<string, mixed>
     */
    private function attributes(SavePolicyRequest $request): array
    {
        $start = Carbon::parse($request->validated('valid_from'));
        $middle = $start->copy()->addMonthsNoOverflow(UnitPolicy::SEMESTER_MONTHS);

        return [
            ...collect($request->validated())->except(['unit_id', 'valid_from'])->all(),
            'endorsement' => $request->boolean('endorsement'),
            'first_payment_starts_on' => $start,
            'first_payment_ends_on' => $middle,
            'second_payment_starts_on' => $middle,
            'second_payment_ends_on' => $middle->copy()->addMonthsNoOverflow(UnitPolicy::SEMESTER_MONTHS),
        ];
    }

    /**
     * Lo que necesita el formulario: las unidades para elegir y las coberturas.
     *
     * @return array<string, mixed>
     */
    private function formOptions(?int $keepUnitId = null): array
    {
        return [
            // Las que están en mantenimiento no se ofrecen; al editar, la de la
            // póliza sí aparece aunque lo esté, para poder mostrarla.
            'units' => Unit::query()
                ->where(fn ($query) => $query
                    ->where('status', '!=', 'maintenance')
                    ->when($keepUnitId, fn ($q) => $q->orWhere('id', $keepUnitId)))
                ->orderBy('brand')
                ->orderBy('model')
                ->get(['id', 'type', 'brand', 'model', 'plate', 'economic_number'])
                ->map(fn (Unit $unit) => [
                    'value' => $unit->id,
                    'label' => collect(["{$unit->brand} {$unit->model}", $unit->plate, $unit->economic_number])->filter()->implode(' · '),
                    'type' => $unit->type,
                ]),
            'coverages' => UnitPolicy::COVERAGES,
        ];
    }

    /**
     * GET /polizas/{policy}/archivos/{evidence}
     *
     * Los comprobantes viven fuera de public: se sirven por aquí, con
     * el nombre con el que se subieron y solo si son de esta póliza.
     */
    public function file(UnitPolicy $policy, UnitEvidence $evidence): StreamedResponse
    {
        abort_unless($evidence->unit_policy_id === $policy->id, 404);
        abort_unless(Storage::disk('local')->exists($evidence->path), 404);

        return Storage::disk('local')->download($evidence->path, $evidence->name);
    }

    /**
     * POST /polizas/{policy}/pagos/{payment}
     *
     * Marca la cuota como pagada con su comprobante.
     */
    public function pay(RegisterPolicyPaymentRequest $request, UnitPolicy $policy, string $payment): RedirectResponse
    {
        DB::transaction(function () use ($request, $policy, $payment) {
            $this->storeFile($request, $policy, $request->file('receipt'), UnitEvidence::PAYMENT_RECEIPT, $payment);

            $policy->update(["{$payment}_payment_paid_at" => $request->validated('paid_at')]);
        });

        $label = $payment === 'first' ? 'primer' : 'segundo';

        return back()->with('success', "Se registró el {$label} pago de la póliza {$policy->policy}.");
    }

    /**
     * Guarda el archivo en disco junto a los de la unidad y deja en la base
     * su ruta, el nombre con el que lo subieron, su periodo y de qué cuota es.
     */
    private function storeFile(Request $request, UnitPolicy $policy, UploadedFile $file, string $type, string $payment): void
    {
        $policy->evidences()->create([
            'unit_id' => $policy->unit_id,
            'type' => $type,
            'payment' => $payment,
            'path' => Storage::disk('local')->putFile("units/{$policy->unit_id}", $file),
            'name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => $request->user()->id,
        ]);
    }

    /**
     * Cómo va una cuota según su fecha límite de pago: pagada, vencida o
     * pendiente. Si la póliza se canceló, lo que faltaba ya no se cobra.
     */
    private function paymentStatus(UnitPolicy $policy, string $payment, bool $cancelled): string
    {
        if ($policy->isPaid($payment)) {
            return 'paid';
        }

        if ($cancelled) {
            return 'cancelled';
        }

        $dueOn = $policy->{"{$payment}_payment_due_on"};

        return $dueOn && $dueOn->lt(today()) ? 'overdue' : 'pending';
    }

    /** Los comprobantes de una cuota, listos para la vista. */
    private function receiptsOf(UnitPolicy $policy, string $payment): Collection
    {
        return $policy->evidences
            ->where('payment', $payment)
            ->values()
            ->map(fn (UnitEvidence $evidence) => $this->fileSummary($evidence));
    }

    /** @return array<string, mixed> */
    private function fileSummary(UnitEvidence $evidence): array
    {
        return [
            'id' => $evidence->id,
            'name' => $evidence->name,
            'size' => $evidence->size,
            'uploaded_by' => $evidence->uploader?->name,
            'created_at' => $evidence->created_at?->toIso8601String(),
        ];
    }

    /**
     * Las pólizas de las unidades, solo el periodo vigente de cada una: los
     * renovados son historial y se ven en la ficha de la unidad.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function policies(): Collection
    {
        return UnitPolicy::with('unit:id,type,brand,model,plate,economic_number')
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
                    'coverage' => $policy->coverage,
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
                'coverage' => null,
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
