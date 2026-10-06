<?php

namespace App\Http\Requests\Policy;

use App\Models\UnitPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Alta y edición de una póliza de unidad. La vigencia se captura con su
 * inicio: los dos semestres salen de ahí, seis meses cada uno.
 *
 * Al editar la unidad no cambia: sus comprobantes ya van ligados a ella.
 */
class SavePolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can($this->editing() ? 'polizas.update' : 'polizas.create') ?? false;
    }

    /** Campos que se guardan siempre en mayúsculas. */
    public const UPPERCASE = ['policy', 'certificate', 'insurer', 'comments'];

    /** El formulario ya los manda así; esto cubre cualquier otra entrada. */
    protected function prepareForValidation(): void
    {
        $this->merge(collect(self::UPPERCASE)
            ->filter(fn (string $field) => is_string($this->input($field)))
            ->mapWithKeys(fn (string $field) => [$field => mb_strtoupper($this->input($field))])
            ->all());
    }

    private function editing(): bool
    {
        return $this->route('policy') instanceof UnitPolicy;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $policy = $this->route('policy');

        return [
            // Una unidad en mantenimiento no se asegura hasta que regrese.
            'unit_id' => $this->editing() ? ['exclude'] : [
                'required',
                'integer',
                Rule::exists('units', 'id')->where(fn ($query) => $query->where('status', '!=', 'maintenance')),
            ],
            'policy' => ['required', 'string', 'max:255', Rule::unique('unit_policies', 'policy')->ignore($policy)],
            'certificate' => ['nullable', 'string', 'max:255'],
            'insurer' => ['nullable', 'string', 'max:255'],
            'coverage' => ['required', Rule::in(UnitPolicy::COVERAGES)],
            'endorsement' => ['boolean'],

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
     * encimarse con otra de la misma unidad que no esté cancelada.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
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
            'unit_id' => 'unidad',
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
