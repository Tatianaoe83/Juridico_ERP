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

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'bond' => ['required', 'string', 'max:255', Rule::unique('bonds', 'bond')->ignore($this->route('bond'))],
            'beneficiary' => ['nullable', 'string', 'max:255'],
            'bonding_company' => ['nullable', 'string', 'max:255'],
            'amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],

            'requested_on' => ['nullable', 'date'],
            'issued_on' => ['nullable', 'date'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],

            'source_document' => ['nullable', 'string', 'max:255'],
            'product' => ['nullable', 'string', 'max:255'],
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
            'bond' => 'número de fianza',
            'beneficiary' => 'beneficiario',
            'bonding_company' => 'afianzadora',
            'amount' => 'monto',
            'requested_on' => 'solicitud de emisión',
            'issued_on' => 'fecha de emisión',
            'valid_from' => 'inicio de vigencia',
            'valid_until' => 'fin de vigencia',
            'source_document' => 'docto. fuente',
            'product' => 'producto',
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
            'valid_until.after_or_equal' => 'El fin de la vigencia no puede ser antes del inicio.',
            'cancelled_on.after_or_equal' => 'La cancelación no puede ser antes de que se solicitara.',
        ];
    }
}
