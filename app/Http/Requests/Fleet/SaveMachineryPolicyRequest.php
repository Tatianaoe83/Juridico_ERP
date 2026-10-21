<?php

namespace App\Http\Requests\Fleet;

use App\Models\Unit;
use App\Models\UnitEvidence;
use App\Models\UnitPolicy;
use App\Rules\ValidDocumentContent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * La póliza de maquinaria con sus máquinas, en el alta y en la edición. Sin
 * placas, asignación ni número de serie: la máquina se identifica por su tipo.
 * Un solo pago y la vigencia completa, sin semestres ni endoso. La unidad de
 * negocio es de la póliza, no de cada máquina.
 */
class SaveMachineryPolicyRequest extends FormRequest
{
    use ValidatesPolicySource;

    /** Campos de la póliza que se guardan siempre en mayúsculas. */
    private const UPPERCASE = ['policy', 'certificate'];

    public function authorize(): bool
    {
        return $this->user()?->can($this->editing() ? 'flotillas.update' : 'flotillas.create') ?? false;
    }

    /** Póliza, certificado, tipo, marca y modelo van en mayúsculas. */
    protected function prepareForValidation(): void
    {
        $upper = fn (mixed $value) => is_string($value) ? mb_strtoupper($value) : $value;

        $this->merge(collect(self::UPPERCASE)
            ->filter(fn (string $field) => $this->has($field))
            ->mapWithKeys(fn (string $field) => [$field => $upper($this->input($field))])
            ->all());

        if (is_array($this->input('machines'))) {
            $this->merge(['machines' => collect($this->input('machines'))
                ->map(fn (mixed $machine) => is_array($machine)
                    ? [...$machine, ...collect(Unit::MACHINE_UPPERCASE)
                        ->filter(fn (string $field) => array_key_exists($field, $machine))
                        ->mapWithKeys(fn (string $field) => [$field => $upper($machine[$field])])
                        ->all()]
                    : $machine)
                ->all()]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $policy = $this->current();

        // Las máquinas que ya son de esta póliza: solo esas se editan o les
        // quitan documentos. El id de otra no pasa.
        $machineIds = $policy ? $policy->machines()->pluck('id')->all() : [];

        // La del proveedor no lleva pago.
        $onlyCorporate = 'exclude_if:source,'.UnitPolicy::SUPPLIER;

        return [
            'policy' => ['required', 'string', 'max:255', Rule::unique('unit_policies', 'policy')->ignore($policy)],
            'certificate' => ['nullable', 'string', 'max:255'],
            'insurer_id' => ['nullable', 'integer', 'exists:insurers,id'],
            'coverage' => ['required', Rule::in(UnitPolicy::COVERAGES[UnitPolicy::MACHINERY])],
            'currency' => [$onlyCorporate, 'required', Rule::in(UnitPolicy::CURRENCIES)],
            'valid_from' => ['required', 'date'],
            'valid_until' => ['required', 'date', 'after:valid_from'],
            'amount' => [$onlyCorporate, 'nullable', 'numeric', 'min:0', 'max:9999999999'],
            'business_unit_id' => ['required', 'integer', 'exists:business_units,id'],
            'comments' => ['nullable', 'string', 'max:5000'],
            ...$this->sourceRules(),

            'machines' => ['required', 'array', 'min:1', 'max:100'],
            'machines.*.id' => ['nullable', 'integer', Rule::in($machineIds)],
            'machines.*.machine_type' => ['required', 'string', 'max:255'],
            'machines.*.brand' => ['required', 'string', 'max:255'],
            'machines.*.model' => ['required', 'string', 'max:255'],
            'machines.*.economic_number' => ['nullable', 'string', 'max:50'],
            'machines.*.status' => ['required', Rule::in(Unit::STATUSES)],

            // Los documentos son de cada máquina.
            'machines.*.evidences' => ['nullable', 'array', 'max:10'],
            'machines.*.evidences.*' => [
                'file',
                'max:10240',
                'extensions:'.UnitEvidence::DOCUMENT_TYPES,
                new ValidDocumentContent,
            ],
            'machines.*.remove_evidences' => ['nullable', 'array'],
            'machines.*.remove_evidences.*' => [
                'integer',
                Rule::exists('unit_evidences', 'id')
                    ->whereIn('unit_id', $machineIds)
                    ->where('type', UnitEvidence::OFFICIAL_DOCUMENT),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'policy' => 'número de póliza',
            'certificate' => 'certificado',
            'insurer_id' => 'aseguradora',
            'coverage' => 'cobertura',
            'currency' => 'moneda',
            'valid_from' => 'inicio de vigencia',
            'valid_until' => 'fin de vigencia',
            'amount' => 'monto a pagar',
            'business_unit_id' => 'unidad de negocio',
            'comments' => 'comentarios',
            'machines' => 'máquinas',
            'machines.*.machine_type' => 'tipo',
            'machines.*.brand' => 'marca',
            'machines.*.model' => 'modelo',
            'machines.*.economic_number' => 'número económico',
            'machines.*.status' => 'estado',
            'machines.*.evidences' => 'documentos',
            'machines.*.evidences.*' => 'documento',
            ...$this->sourceAttributes(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'policy.unique' => 'Ya hay una póliza con ese número.',
            'valid_until.after' => 'El fin de vigencia tiene que ser después del inicio.',
            'machines.required' => 'Agrega al menos una máquina.',
            'machines.min' => 'Agrega al menos una máquina.',
            'machines.max' => 'Una póliza puede llevar hasta 100 máquinas.',
            ...$this->sourceMessages(),
            'machines.*.evidences.*.extensions' => 'Solo se aceptan PDF, Word, Excel, imágenes (JPG, PNG, WEBP) y XML.',
            'machines.*.evidences.*.max' => 'Cada archivo puede pesar hasta 10 MB.',
        ];
    }

    /** La póliza que se edita; null en el alta. */
    public function current(): ?UnitPolicy
    {
        $policy = $this->route('policy');

        return $policy instanceof UnitPolicy ? $policy : null;
    }

    private function editing(): bool
    {
        return $this->current() !== null;
    }
}
