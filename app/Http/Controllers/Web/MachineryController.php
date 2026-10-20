<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fleet\SaveMachineryPolicyRequest;
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

/**
 * Flotillas, maquinaria: una póliza cubre varias máquinas. La póliza y sus
 * máquinas se dan de alta y se editan juntas; cada máquina guarda sus propios
 * documentos. Un solo pago, en dólares si es EC y en pesos si es RC. La
 * unidad de negocio es de la póliza: sus máquinas no llevan la suya.
 */
class MachineryController extends Controller
{
    /** Campos de cada máquina que se guardan en `units`. */
    private const MACHINE_FIELDS = ['machine_type', 'brand', 'model', 'economic_number', 'status'];

    /** La fecha límite y el fin de vigencia se agendan en Outlook. */
    public function __construct(private readonly UnitCalendar $calendar) {}

    /** POST /flotillas/maquinaria */
    public function store(SaveMachineryPolicyRequest $request): RedirectResponse
    {
        $policy = DB::transaction(function () use ($request) {
            $policy = UnitPolicy::create([
                ...$this->policyAttributes($request),
                'kind' => UnitPolicy::MACHINERY,
                'created_by' => $request->user()->id,
            ]);

            $this->saveMachines($request, $policy);

            return $policy;
        });

        $agendada = $this->calendar->sync($policy, $request->user());

        return to_route('fleets.index', FleetController::tabOf($policy))
            ->with('success', "Se registró la póliza de maquinaria {$policy->policy}.".$this->calendarNote($agendada));
    }

    /**
     * GET /flotillas/maquinaria/{policy}
     *
     * La póliza y cada máquina con sus documentos.
     */
    public function show(UnitPolicy $policy): Response
    {
        $this->ensureMachinery($policy);

        $policy->load(['businessUnit', 'machines.evidences' => fn ($query) => $query
            ->where('type', UnitEvidence::OFFICIAL_DOCUMENT)
            ->with('uploader:id,name')
            ->orderBy('id')]);

        return Inertia::render('Fleets/Machinery/Show', [
            'policy' => [
                ...self::summary($policy),
                ...FleetController::sourceDetail($policy),
                'certificate' => $policy->certificate,
                'comments' => $policy->comments,
                'machines' => $policy->machines->map(fn (Unit $machine) => [
                    ...self::machineSummary($machine),
                    'documents' => $machine->evidences->map(fn (UnitEvidence $evidence) => [
                        'id' => $evidence->id,
                        'name' => $evidence->name,
                        'size' => $evidence->size,
                        'uploaded_by' => $evidence->uploader?->name,
                        'created_at' => $evidence->created_at?->toIso8601String(),
                    ]),
                ]),
            ],
        ]);
    }

    /** GET /flotillas/maquinaria/{policy}/editar */
    public function edit(UnitPolicy $policy): Response
    {
        $this->ensureMachinery($policy);

        $policy->load(['machines.evidences' => fn ($query) => $query
            ->where('type', UnitEvidence::OFFICIAL_DOCUMENT)
            ->orderBy('id')]);

        return Inertia::render('Fleets/Machinery/Edit', [
            'policy' => [
                'id' => $policy->id,
                'policy' => $policy->policy,
                'certificate' => $policy->certificate,
                'insurer' => $policy->insurer,
                'coverage' => $policy->coverage,
                'currency' => $policy->currency,
                'business_unit_id' => $policy->business_unit_id,
                ...FleetController::sourceDetail($policy),
                'valid_from' => $policy->first_payment_starts_on?->toDateString(),
                'valid_until' => $policy->second_payment_ends_on?->toDateString(),
                'amount' => $policy->first_payment_amount,
                'comments' => $policy->comments,
                'machines' => $policy->machines->map(fn (Unit $machine) => [
                    'id' => $machine->id,
                    'machine_type' => $machine->machine_type,
                    'brand' => $machine->brand,
                    'model' => $machine->model,
                    'economic_number' => $machine->economic_number,
                    'status' => $machine->status,
                    'evidences' => $machine->evidences->map(fn (UnitEvidence $evidence) => $evidence->only(['id', 'name', 'size'])),
                ]),
            ],
            ...$this->formOptions(),
        ]);
    }

    /** PATCH /flotillas/maquinaria/{policy} */
    public function update(SaveMachineryPolicyRequest $request, UnitPolicy $policy): RedirectResponse
    {
        $this->ensureMachinery($policy);

        DB::transaction(function () use ($request, $policy) {
            $policy->fill($this->policyAttributes($request))->save();

            $this->saveMachines($request, $policy);
        });

        $note = UnitCalendar::needsSync($policy) ? $this->calendarNote($this->calendar->sync($policy, $request->user())) : '';

        return to_route('fleets.index', FleetController::tabOf($policy))
            ->with('success', "Se actualizó la póliza de maquinaria {$policy->policy}.".$note);
    }

    /** DELETE /flotillas/maquinaria/{policy}: la póliza se va con sus máquinas. */
    public function destroy(Request $request, UnitPolicy $policy): RedirectResponse
    {
        $this->ensureMachinery($policy);

        // Primero Outlook: después del delete ya no hay de dónde sacar los ids.
        $this->calendar->forgetPolicy($policy, $request->user());

        DB::transaction(function () use ($policy) {
            foreach ($policy->machines as $machine) {
                $this->deleteMachine($machine);
            }

            $policy->delete();
        });

        return to_route('fleets.index', FleetController::tabOf($policy))
            ->with('success', "Se eliminó la póliza de maquinaria {$policy->policy}.");
    }

    /**
     * Lo que ve la tabla de maquinaria: la póliza, su pago y su vigencia, y
     * sus máquinas para desplegarlas en la misma fila.
     *
     * @return array<string, mixed>
     */
    public static function summary(UnitPolicy $policy): array
    {
        return [
            'id' => $policy->id,
            'policy' => $policy->policy,
            'insurer' => $policy->insurer,
            'coverage' => $policy->coverage,
            'business_unit' => $policy->businessUnit?->name,
            'source' => $policy->source ?? UnitPolicy::CORPORATE,
            'currency' => $policy->currency,
            'amount' => $policy->first_payment_amount !== null ? (float) $policy->first_payment_amount : null,
            'valid_until' => $policy->second_payment_ends_on?->toDateString(),
            'status' => $policy->coverageStatus(),
            'machines' => $policy->machines->map(fn (Unit $machine) => self::machineSummary($machine)),
        ];
    }

    /** @return array<string, mixed> */
    private static function machineSummary(Unit $machine): array
    {
        return [
            'id' => $machine->id,
            'machine_type' => $machine->machine_type,
            'brand' => $machine->brand,
            'model' => $machine->model,
            'economic_number' => $machine->economic_number,
            'status' => $machine->status,
        ];
    }

    /**
     * La captura llevada a las columnas de siempre: la vigencia completa en
     * first_payment_starts_on / second_payment_ends_on y el único pago en
     * first_payment_*. La moneda la elige quien captura, según el contrato.
     *
     * @return array<string, mixed>
     */
    private function policyAttributes(SaveMachineryPolicyRequest $request): array
    {
        return [
            ...$request->safe()->only(['policy', 'certificate', 'insurer', 'coverage', 'business_unit_id', 'comments']),
            ...$request->policySource(),
            // La del proveedor no lleva pago: moneda y monto quedan vacíos.
            'currency' => $request->validated('currency'),
            'first_payment_starts_on' => $request->validated('valid_from'),
            'second_payment_ends_on' => $request->validated('valid_until'),
            'first_payment_amount' => $request->validated('amount'),
            // Sin fecha límite de pago: no se captura ni se agenda.
            'first_payment_due_on' => null,
        ];
    }

    /**
     * Crea las máquinas nuevas, actualiza las que ya estaban y borra las que
     * se quitaron del formulario, cada una con sus documentos.
     */
    private function saveMachines(SaveMachineryPolicyRequest $request, UnitPolicy $policy): void
    {
        $kept = [];

        foreach ($request->validated('machines') as $index => $data) {
            $attributes = Arr::only($data, self::MACHINE_FIELDS);

            $machine = isset($data['id'])
                ? tap($policy->machines()->findOrFail($data['id']))->update($attributes)
                : $policy->machines()->create([
                    ...$attributes,
                    'type' => Unit::MACHINERY,
                    'created_by' => $request->user()->id,
                ]);

            $kept[] = $machine->id;

            foreach ($machine->evidences()->whereIn('id', $data['remove_evidences'] ?? [])->get() as $evidence) {
                Storage::disk('local')->delete($evidence->path);
                $evidence->delete();
            }

            foreach ($request->file("machines.{$index}.evidences", []) as $file) {
                $this->storeEvidence($request, $machine, $file);
            }
        }

        $policy->machines()->whereNotIn('id', $kept)->get()->each(fn (Unit $machine) => $this->deleteMachine($machine));

        $policy->unsetRelation('machines');
    }

    /** Los archivos no se van con la fila: el disco no sabe de llaves foráneas. */
    private function deleteMachine(Unit $machine): void
    {
        foreach ($machine->evidences as $evidence) {
            Storage::disk('local')->delete($evidence->path);
        }

        $machine->delete();
    }

    private function storeEvidence(Request $request, Unit $machine, UploadedFile $file): void
    {
        $machine->evidences()->create([
            'type' => UnitEvidence::OFFICIAL_DOCUMENT,
            'path' => Storage::disk('local')->putFile("units/{$machine->id}", $file),
            'name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => $request->user()->id,
        ]);
    }

    /** Las vehiculares tienen su propia pantalla. */
    private function ensureMachinery(UnitPolicy $policy): void
    {
        abort_unless($policy->isMachinery(), 404);
    }

    /** @return array<string, mixed> */
    public static function formOptions(): array
    {
        return [
            'businessUnits' => BusinessUnit::orderBy('name')->get(['id', 'name']),
            'statuses' => Unit::STATUSES,
            'machineryCoverages' => UnitPolicy::COVERAGES[UnitPolicy::MACHINERY],
            'machineryCurrencies' => UnitPolicy::MACHINERY_CURRENCIES,
            'currencies' => UnitPolicy::CURRENCIES,
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
}
