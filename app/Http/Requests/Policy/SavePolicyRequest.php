<?php

namespace App\Http\Requests\Policy;

use App\Models\ConstructionPolicy;
use App\Models\UnitEvidence;
use App\Rules\ValidDocumentContent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta y edición de una póliza de obra. La vigencia se captura completa: el
 * fin es la terminación de la obra o, en algunos casos, un año exacto.
 *
 * Con endosos el fin ya no se edita aquí: lo recorre el último endoso. La obra
 * sí se puede corregir: por ahora es texto libre.
 */
class SavePolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can($this->editing() ? 'polizas.update' : 'polizas.create') ?? false;
    }

    /** Campos que se guardan siempre en mayúsculas. */
    public const UPPERCASE = ['policy', 'project', 'project_address', 'comments'];

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
        return $this->route('policy') instanceof ConstructionPolicy;
    }

    /** Si ya tiene endosos: entonces su fin es el del último. */
    public function endorsed(): bool
    {
        return $this->editing() && $this->route('policy')->endorsements()->exists();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $policy = $this->route('policy');

        return [
            'project' => ['required', 'string', 'max:255'],
            'project_address' => ['nullable', 'string', 'max:255'],
            'business_unit_id' => ['required', 'integer', 'exists:business_units,id'],
            'policy' => ['required', 'string', 'max:255', Rule::unique('construction_policies', 'policy')->ignore($policy)],
            'insurer_id' => ['nullable', 'integer', 'exists:insurers,id'],
            'coverage' => ['required', Rule::in(ConstructionPolicy::COVERAGES)],

            // Con endosos, el inicio no puede pasar del fin que ya tiene.
            'valid_from' => [
                'required',
                'date',
                ...($this->endorsed() ? ['before_or_equal:'.$policy->valid_until->toDateString()] : []),
            ],
            'valid_until' => $this->endorsed() ? ['exclude'] : ['required', 'date', 'after_or_equal:valid_from'],
            'amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'comments' => ['nullable', 'string', 'max:5000'],

            // Los mismos tipos que los documentos de Flotillas, y el contenido
            // tiene que ser de verdad lo que dice la extensión.
            'documents' => ['nullable', 'array', 'max:10'],
            'documents.*' => ['file', 'max:10240', 'extensions:'.UnitEvidence::DOCUMENT_TYPES, new ValidDocumentContent],

            // Solo se pueden quitar documentos de esta póliza: el id de otra no
            // pasa la regla y no se borra nada ajeno.
            'remove_documents' => $this->editing() ? ['nullable', 'array'] : ['exclude'],
            'remove_documents.*' => [
                'integer',
                Rule::exists('construction_policy_documents', 'id')->where('construction_policy_id', $policy?->id),
            ],
        ];
    }

    /**
     * Lo que se guarda en la póliza: los documentos van en su propia tabla.
     *
     * @return array<string, mixed>
     */
    public function policyAttributes(): array
    {
        return $this->safe()->except(['documents', 'remove_documents']);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'project' => 'obra',
            'project_address' => 'dirección de la obra',
            'business_unit_id' => 'unidad de negocio',
            'policy' => 'número de póliza',
            'insurer_id' => 'aseguradora',
            'coverage' => 'cobertura',
            'valid_from' => 'inicio de vigencia',
            'valid_until' => 'fin de vigencia',
            'amount' => 'monto',
            'comments' => 'comentarios',
            'documents' => 'documentos',
            'documents.*' => 'documento',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'policy.unique' => 'Ya hay una póliza con ese número.',
            'valid_from.before_or_equal' => 'El inicio no puede pasar del fin de vigencia que dejó el último endoso.',
            'valid_until.after_or_equal' => 'El fin de vigencia no puede ser antes del inicio.',
            'documents.*.extensions' => 'Solo se aceptan PDF, Word, Excel, imágenes (JPG, PNG, WEBP) y XML.',
            'documents.*.max' => 'Cada archivo puede pesar hasta 10 MB.',
        ];
    }
}
