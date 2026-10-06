<?php

namespace App\Http\Requests\Policy;

use App\Models\UnitPolicy;
use App\Rules\ValidDocumentContent;
use App\Support\CoverageStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Registrar el pago de una cuota: el comprobante y el día en que se pagó.
 * No es un abono, no lleva monto: vale el importe de la cuota.
 */
class RegisterPolicyPaymentRequest extends FormRequest
{
    /** Lo que se acepta como comprobante: PDF o imagen. */
    public const RECEIPT_TYPES = 'pdf,jpg,jpeg,png,webp';

    public function authorize(): bool
    {
        return $this->user()?->can('polizas.update') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'receipt' => ['required', 'file', 'max:10240', 'extensions:'.self::RECEIPT_TYPES, new ValidDocumentContent],
            'paid_at' => ['required', 'date', 'before_or_equal:today'],
        ];
    }

    /**
     * Cada cuota se paga una vez y una póliza cancelada ya no se paga. Va aquí
     * para que el error salga junto al botón, como los demás.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                /** @var UnitPolicy $policy */
                $policy = $this->route('policy');

                if ($policy->coverageStatus() === CoverageStatus::CANCELLED) {
                    $validator->errors()->add('receipt', 'La póliza está cancelada: ya no se registran pagos.');
                } elseif ($policy->isPaid($this->route('payment'))) {
                    $validator->errors()->add('receipt', 'Ese pago ya está registrado.');
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
            'receipt' => 'comprobante',
            'paid_at' => 'fecha de pago',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'receipt.extensions' => 'El comprobante debe ser PDF o imagen.',
            'receipt.max' => 'El comprobante puede pesar hasta 10 MB.',
            'paid_at.before_or_equal' => 'La fecha de pago no puede ser futura.',
        ];
    }
}
