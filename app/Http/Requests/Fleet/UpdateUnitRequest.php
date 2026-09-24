<?php

namespace App\Http\Requests\Fleet;

use App\Models\Unit;
use App\Models\UnitEvidence;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUnitRequest extends FormRequest
{
    /**
     * La autorización va aquí y no solo en la ruta porque el FormRequest
     * valida antes que el controlador: sin esto, quien no tiene permiso
     * recibiría un 422 con las reglas en lugar de un 403.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('flotillas.update') ?? false;
    }

    /** Igual que en el alta: esos campos se guardan siempre en mayúsculas. */
    protected function prepareForValidation(): void
    {
        $this->merge(collect(Unit::UPPERCASE)
            ->filter(fn (string $field) => is_string($this->input($field)))
            ->mapWithKeys(fn (string $field) => [$field => mb_strtoupper($this->input($field))])
            ->all());
    }

    /**
     * Las mismas reglas del alta; lo único que cambia es que el número de
     * serie y la placa se comparan contra las demás unidades, no contra esta.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Unit $unit */
        $unit = $this->route('unit');

        return [
            'business_unit_id' => ['required', 'integer', 'exists:business_units,id'],
            'brand' => ['required', 'string', 'max:255'],
            'model' => ['required', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:255', Rule::unique('units', 'serial_number')->ignore($unit)],
            'plate' => ['nullable', 'string', 'max:50', Rule::unique('units', 'plate')->ignore($unit)],
            'economic_number' => ['nullable', 'string', 'max:50'],
            'responsible' => ['nullable', 'string', 'max:255'],

            'status' => ['required', Rule::in(Unit::STATUSES)],
            'comments' => ['nullable', 'string', 'max:5000'],

            'evidences' => ['nullable', 'array', 'max:10'],
            'evidences.*' => ['file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx'],

            // Solo se pueden quitar documentos de esta unidad: el id de otra no
            // pasa la regla y no se borra nada ajeno. Los comprobantes de pago
            // tampoco: son la prueba de que se pagó.
            'remove_evidences' => ['nullable', 'array'],
            'remove_evidences.*' => [
                'integer',
                Rule::exists('unit_evidences', 'id')
                    ->where('unit_id', $unit->id)
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
            'serial_number.unique' => 'Ya hay otra unidad con ese número de serie.',
            'plate.unique' => 'Ya hay otra unidad con esa placa.',
            'evidences.*.mimes' => 'Solo se aceptan PDF, imágenes y documentos de Office.',
            'evidences.*.max' => 'Cada archivo puede pesar hasta 10 MB.',
        ];
    }
}
