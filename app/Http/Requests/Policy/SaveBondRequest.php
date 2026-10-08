<?php

namespace App\Http\Requests\Policy;

use App\Models\Bond;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Alta y edición de una fianza. */
class SaveBondRequest extends FormRequest
{
    public function authorize(): bool
    {
        $editing = $this->route('bond') instanceof Bond;

        return $this->user()?->can($editing ? 'polizas.update' : 'polizas.create') ?? false;
    }

    /** Campos que se guardan siempre en mayúsculas. */
    public const UPPERCASE = ['bond', 'bonding_company', 'beneficiary', 'related', 'source_document', 'comments'];

    /** El formulario ya los manda así; esto cubre cualquier otra entrada. */
    protected function prepareForValidation(): void
    {
        $this->merge(collect(self::UPPERCASE)
            ->filter(fn (string $field) => is_string($this->input($field)))
            ->mapWithKeys(fn (string $field) => [$field => mb_strtoupper($this->input($field))])
            ->all());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'business_unit_id' => ['required', 'integer', 'exists:business_units,id'],
            'bond' => ['required', 'string', 'max:255', Rule::unique('bonds', 'bond')->ignore($this->route('bond'))],
            'beneficiary' => ['nullable', 'string', 'max:255'],
            'bonding_company' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0', 'max:999999999999'],

            'requested_on' => ['nullable', 'date'],
            'issued_on' => ['nullable', 'date', 'after_or_equal:requested_on'],
            // Sin regla contra la emisión: la vigencia sigue al contrato y la
            // fianza suele emitirse días después de firmarlo, con vigencia
            // desde la firma.
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],

            'source_document' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', Rule::in(Bond::CATEGORIES)],
            'related' => ['nullable', 'string', 'max:255'],

            'cancellation_requested_on' => ['nullable', 'date'],
            'cancelled_on' => ['nullable', 'date', 'after_or_equal:cancellation_requested_on'],
            'comments' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'business_unit_id' => 'unidad de negocio',
            'bond' => 'número de fianza',
            'beneficiary' => 'beneficiario',
            'bonding_company' => 'afianzadora',
            'amount' => 'monto',
            'requested_on' => 'solicitud de emisión',
            'issued_on' => 'fecha de emisión',
            'valid_from' => 'inicio de vigencia',
            'valid_until' => 'fin de vigencia',
            'source_document' => 'docto. fuente',
            'category' => 'categoría',
            'related' => 'relativo',
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
            'bond.unique' => 'Ya hay una fianza con ese número.',
            'issued_on.after_or_equal' => 'La emisión no puede ser antes de que se solicitara.',
            'valid_until.after_or_equal' => 'El fin de la vigencia no puede ser antes del inicio.',
            'cancelled_on.after_or_equal' => 'La cancelación no puede ser antes de que se solicitara.',
        ];
    }
}
