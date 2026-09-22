<?php

namespace App\Http\Requests\Fleet;

use App\Models\Unit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUnitRequest extends FormRequest
{
    /**
     * La autorización va aquí y no solo en la ruta porque el FormRequest
     * valida antes que el controlador: sin esto, quien no tiene permiso
     * recibiría un 422 con las reglas en lugar de un 403.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('flotillas.create') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'policy' => ['required', 'string', 'max:255', 'unique:units,policy'],
            'certificate' => ['nullable', 'string', 'max:255'],
            'business_unit_id' => ['nullable', 'integer', 'exists:business_units,id'],
            'brand' => ['required', 'string', 'max:255'],
            'model' => ['required', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:255', 'unique:units,serial_number'],
            'plate' => ['nullable', 'string', 'max:50'],
            'economic_number' => ['nullable', 'string', 'max:50'],
            'responsible' => ['nullable', 'string', 'max:255'],

            // Los dos semestres son opcionales, pero si se captura el rango
            // tiene que cerrar: el fin nunca antes del inicio.
            'first_payment_starts_on' => ['nullable', 'date'],
            'first_payment_ends_on' => ['nullable', 'date', 'after_or_equal:first_payment_starts_on'],
            'first_payment_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'second_payment_starts_on' => ['nullable', 'date'],
            'second_payment_ends_on' => ['nullable', 'date', 'after_or_equal:second_payment_starts_on'],
            'second_payment_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],

            'usa_canada_endorsement' => ['boolean'],
            'status' => ['required', Rule::in(Unit::STATUSES)],
            'comments' => ['nullable', 'string', 'max:5000'],

            // Evidencias: lo que respalda a la unidad. Van opcionales porque
            // el alta no puede quedarse trabada esperando un archivo.
            'evidences' => ['nullable', 'array', 'max:10'],
            'evidences.*' => ['file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'policy' => 'póliza',
            'certificate' => 'certificado',
            'business_unit_id' => 'unidad de negocio',
            'brand' => 'marca',
            'model' => 'modelo',
            'serial_number' => 'número de serie',
            'plate' => 'placa',
            'economic_number' => 'número económico',
            'responsible' => 'responsable',
            'first_payment_starts_on' => 'inicio del primer pago',
            'first_payment_ends_on' => 'fin del primer pago',
            'first_payment_amount' => 'importe del primer pago',
            'second_payment_starts_on' => 'inicio del segundo pago',
            'second_payment_ends_on' => 'fin del segundo pago',
            'second_payment_amount' => 'importe del segundo pago',
            'status' => 'estado',
            'comments' => 'comentarios',
            'evidences' => 'evidencias',
            'evidences.*' => 'evidencia',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'policy.unique' => 'Ya hay una unidad con esa póliza.',
            'serial_number.unique' => 'Ya hay una unidad con ese número de serie.',
            'evidences.*.mimes' => 'Solo se aceptan PDF, imágenes y documentos de Office.',
            'evidences.*.max' => 'Cada archivo puede pesar hasta 10 MB.',
        ];
    }
}
