<?php

namespace App\Http\Requests\Fleet;

use App\Models\UnitPolicy;
use Illuminate\Validation\Rule;

/**
 * La póliza vehicular se captura con su unidad, en el mismo formulario. Aquí
 * van sus reglas, las mismas en el alta y en la edición. Solo se capturan los
 * importes: la vehicular no lleva fechas límite de pago ni las agenda.
 *
 * La empresarial dura un año en dos semestres con sus pagos; la del proveedor
 * lleva su propia vigencia (puede ser de más de un año) y un pago único anual,
 * que va en las columnas del primer pago. La del proveedor (con beneficiario
 * preferente) solo existe si la unidad está financiada.
 */
trait ValidatesUnitPolicy
{
    use ValidatesPolicySource;

    /** Campos de la póliza que se guardan siempre en mayúsculas. */
    private const POLICY_UPPERCASE = ['policy', 'certificate'];

    /** Los de la póliza, para separarlos de los de la unidad al guardar. */
    public const POLICY_FIELDS = [
        'policy', 'certificate', 'insurer_id', 'coverage', 'endorsement', 'financing',
        'valid_from', 'valid_until', 'first_payment_amount', 'second_payment_amount',
        'source', 'supplier_id',
    ];

    /** @return array<string, string> */
    private function uppercasePolicy(): array
    {
        return collect(self::POLICY_UPPERCASE)
            ->filter(fn (string $field) => is_string($this->input($field)))
            ->mapWithKeys(fn (string $field) => [$field => mb_strtoupper($this->input($field))])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function policyRules(?UnitPolicy $current = null): array
    {
        $onlyCorporate = 'exclude_if:source,'.UnitPolicy::SUPPLIER;

        return [
            'policy' => ['required', 'string', 'max:255', Rule::unique('unit_policies', 'policy')->ignore($current)],
            'certificate' => ['nullable', 'string', 'max:255'],
            'insurer_id' => ['nullable', 'integer', 'exists:insurers,id'],
            'coverage' => ['required', Rule::in(UnitPolicy::COVERAGES[UnitPolicy::VEHICLE])],
            'endorsement' => ['boolean'],
            'financing' => ['boolean', 'accepted_if:source,'.UnitPolicy::SUPPLIER],
            'valid_from' => ['required', 'date'],
            // La del proveedor dice hasta cuándo; la empresarial siempre es un año.
            'valid_until' => ['exclude_unless:source,'.UnitPolicy::SUPPLIER, 'required', 'date', 'after:valid_from'],
            // En la del proveedor el primer pago es el pago único anual.
            'first_payment_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'second_payment_amount' => [$onlyCorporate, 'nullable', 'numeric', 'min:0', 'max:9999999999'],
            ...$this->sourceRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function policyAttributes(): array
    {
        return [
            'policy' => 'número de póliza',
            'certificate' => 'certificado',
            'insurer_id' => 'aseguradora',
            'coverage' => 'cobertura',
            'endorsement' => 'endoso',
            'financing' => 'financiamiento',
            'valid_from' => 'inicio de vigencia',
            'valid_until' => 'fin de vigencia',
            'first_payment_amount' => $this->fromSupplier() ? 'monto a pagar' : 'importe del primer semestre',
            'second_payment_amount' => 'importe del segundo semestre',
            ...$this->sourceAttributes(),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function policyMessages(): array
    {
        return [
            'valid_until.after' => 'El fin de vigencia tiene que ser después del inicio.',
            'financing.accepted_if' => 'Con beneficiario preferente la unidad tiene que estar financiada.',
            ...$this->sourceMessages(),
        ];
    }
}
