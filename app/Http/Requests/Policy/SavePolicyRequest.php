<?php

namespace App\Http\Requests\Policy;

use App\Models\UnitPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Alta y edición de una póliza, vehicular o de obra. La vigencia se captura
 * con su inicio: los dos semestres salen de ahí, seis meses cada uno.
 *
 * Al editar no cambian el tipo ni la unidad: sus comprobantes ya van ligados
 * a ella. La obra sí se puede corregir: por ahora es texto libre.
 */
class SavePolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can($this->editing() ? 'polizas.update' : 'polizas.create') ?? false;
    }

    /** Campos que se guardan siempre en mayúsculas. */
    public const UPPERCASE = ['policy', 'certificate', 'insurer', 'project', 'project_address', 'comments'];

    /**
     * El formulario ya los manda así; esto cubre cualquier otra entrada. Al
     * editar, el tipo es el guardado.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(collect(self::UPPERCASE)
            ->filter(fn (string $field) => is_string($this->input($field)))
            ->mapWithKeys(fn (string $field) => [$field => mb_strtoupper($this->input($field))])
            ->all());

        if ($this->editing()) {
            $this->merge(['kind' => $this->route('policy')->kind]);
        }
    }

    private function editing(): bool
    {
        return $this->route('policy') instanceof UnitPolicy;
    }

    /** El tipo de la póliza: el que se eligió o, si no llega, vehicular. */
    public function kind(): string
    {
        return in_array($this->input('kind'), UnitPolicy::KINDS, true) ? $this->input('kind') : UnitPolicy::VEHICLE;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $policy = $this->route('policy');
        $construction = $this->kind() === UnitPolicy::CONSTRUCTION;

        return [
            'kind' => $this->editing() ? ['exclude'] : ['required', Rule::in(UnitPolicy::KINDS)],
            // Una unidad en mantenimiento no se asegura hasta que regrese.
            'unit_id' => $this->editing() || $construction ? ['exclude'] : [
                'required',
                'integer',
                Rule::exists('units', 'id')->where(fn ($query) => $query->where('status', '!=', 'maintenance')),
            ],
            // La obra, solo en las de obra.
            'project' => $construction ? ['required', 'string', 'max:255'] : ['exclude'],
            'project_address' => $construction ? ['nullable', 'string', 'max:255'] : ['exclude'],
            'business_unit_id' => $construction ? ['required', 'integer', 'exists:business_units,id'] : ['exclude'],
            'policy' => ['required', 'string', 'max:255', Rule::unique('unit_policies', 'policy')->ignore($policy)],
            'certificate' => ['nullable', 'string', 'max:255'],
            'insurer' => ['nullable', 'string', 'max:255'],
            'coverage' => ['required', Rule::in(UnitPolicy::COVERAGES[$this->kind()])],
            // El endoso USA/Canadá es para circular allá: una obra no se mueve.
            'endorsement' => $construction ? ['exclude'] : ['boolean'],

            'valid_from' => ['required', 'date'],
            'first_payment_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'first_payment_due_on' => ['nullable', 'date'],
            'second_payment_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'second_payment_due_on' => ['nullable', 'date'],

            // La solicitud de cancelación no se captura en el formulario: la
            // llenará su propio flujo. La cancelación se compara con la guardada.
            'cancellation_requested_on' => ['exclude'],
            'cancelled_on' => [
                'nullable',
                'date',
                ...($policy?->cancellation_requested_on ? ['after_or_equal:'.$policy->cancellation_requested_on->toDateString()] : []),
            ],
            'comments' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * Una unidad tiene una sola póliza a la vez: la vigencia nueva no puede
     * encimarse con otra de la misma unidad que no esté cancelada. La obra
     * es texto libre: ahí no hay con qué comparar.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty() || $this->kind() === UnitPolicy::CONSTRUCTION) {
                    return;
                }

                $policy = $this->route('policy');
                $unitId = $policy?->unit_id ?? $this->integer('unit_id');
                $start = Carbon::parse($this->input('valid_from'))->startOfDay();
                $end = $start->copy()->addMonthsNoOverflow(UnitPolicy::SEMESTER_MONTHS * 2);

                $overlap = UnitPolicy::query()
                    ->where('unit_id', $unitId)
                    ->when($policy, fn ($query) => $query->whereKeyNot($policy->id))
                    ->whereNull('cancelled_on')
                    ->where('first_payment_starts_on', '<', $end)
                    ->where('second_payment_ends_on', '>', $start)
                    ->first();

                if ($overlap) {
                    $validator->errors()->add('valid_from', "La unidad ya tiene la póliza {$overlap->policy} en esas fechas.");
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'kind' => 'tipo de póliza',
            'unit_id' => 'unidad',
            'project' => 'obra',
            'project_address' => 'dirección de la obra',
            'business_unit_id' => 'unidad de negocio',
            'policy' => 'número de póliza',
            'certificate' => 'certificado',
            'insurer' => 'aseguradora',
            'coverage' => 'cobertura',
            'endorsement' => 'endoso',
            'valid_from' => 'inicio de vigencia',
            'first_payment_amount' => 'importe del primer pago',
            'first_payment_due_on' => 'fecha límite del primer pago',
            'second_payment_amount' => 'importe del segundo pago',
            'second_payment_due_on' => 'fecha límite del segundo pago',
            'cancellation_requested_on' => 'solicitud de cancelación',
            'cancelled_on' => 'fecha de cancelación',
            'comments' => 'comentarios',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'unit_id.exists' => 'La unidad no existe o está en mantenimiento.',
            'policy.unique' => 'Ya hay una póliza con ese número.',
            'cancelled_on.after_or_equal' => 'La cancelación no puede ser antes de que se solicitara.',
        ];
    }
}
