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
     * Marca, modelo, placa y responsable se guardan siempre en mayúsculas.
     * El formulario ya los manda así; esto cubre cualquier otra entrada.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(collect(Unit::UPPERCASE)
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
            'brand' => ['required', 'string', 'max:255'],
            'model' => ['required', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:255', 'unique:units,serial_number'],
            'plate' => ['nullable', 'string', 'max:50', 'unique:units,plate'],
            'economic_number' => ['nullable', 'string', 'max:50'],
            'responsible' => ['nullable', 'string', 'max:255'],

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
            'business_unit_id' => 'unidad de negocio',
            'brand' => 'marca',
            'model' => 'modelo',
            'serial_number' => 'número de serie',
            'plate' => 'placa',
            'economic_number' => 'número económico',
            'responsible' => 'responsable',
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
            'serial_number.unique' => 'Ya hay una unidad con ese número de serie.',
            'plate.unique' => 'Ya hay una unidad con esa placa.',
            'evidences.*.mimes' => 'Solo se aceptan PDF, imágenes y documentos de Office.',
            'evidences.*.max' => 'Cada archivo puede pesar hasta 10 MB.',
        ];
    }
}
