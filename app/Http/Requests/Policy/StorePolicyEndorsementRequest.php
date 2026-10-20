<?php

namespace App\Http\Requests\Policy;

use App\Models\ConstructionPolicyEndorsement;
use App\Rules\ValidDocumentContent;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Registrar un endoso de extensión en una póliza de obra: el nuevo fin de
 * vigencia, su número si trae y el documento. Solo extiende: el fin nuevo
 * tiene que ser después del que ya tiene.
 */
class StorePolicyEndorsementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('polizas.update') ?? false;
    }

    /** El formulario ya lo manda así; esto cubre cualquier otra entrada. */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('endorsement'))) {
            $this->merge(['endorsement' => mb_strtoupper($this->input('endorsement'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'endorsement' => ['nullable', 'string', 'max:255'],
            'valid_until' => ['required', 'date', 'after:'.$this->route('policy')->valid_until->toDateString()],
            // La prima adicional por extender, con IVA incluido.
            'amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'document' => ['nullable', 'file', 'max:10240', 'extensions:'.ConstructionPolicyEndorsement::DOCUMENT_TYPES, new ValidDocumentContent],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'endorsement' => 'número de endoso',
            'valid_until' => 'nuevo fin de vigencia',
            'amount' => 'monto',
            'document' => 'documento',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'valid_until.after' => 'El nuevo fin tiene que ser después del fin actual ('.$this->route('policy')->valid_until->format('d/m/Y').').',
            'document.extensions' => 'El documento debe ser PDF o imagen.',
            'document.max' => 'El documento puede pesar hasta 10 MB.',
        ];
    }
}
