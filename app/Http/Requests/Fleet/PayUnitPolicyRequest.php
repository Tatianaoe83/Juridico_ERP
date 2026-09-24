<?php

namespace App\Http\Requests\Fleet;

use App\Models\UnitPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Registrar un pago semestral: el comprobante y el día en que se pagó. No es
 * un abono, no lleva monto: vale el importe del periodo.
 *
 * El segundo pago cierra el periodo y abre el siguiente, así que además pide
 * la póliza nueva, que cambia en cada renovación, y los importes que siguen.
 */
class PayUnitPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('flotillas.update') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $renews = $this->route('payment') === 'second';

        return [
            'receipt' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp'],
            'paid_at' => ['required', 'date', 'before_or_equal:today'],

            'new_policy' => $renews ? ['required', 'string', 'max:255', 'unique:unit_policies,policy'] : ['exclude'],
            'new_certificate' => $renews ? ['nullable', 'string', 'max:255'] : ['exclude'],
            'new_first_payment_amount' => $renews ? ['nullable', 'numeric', 'min:0', 'max:9999999999'] : ['exclude'],
            'new_second_payment_amount' => $renews ? ['nullable', 'numeric', 'min:0', 'max:9999999999'] : ['exclude'],
        ];
    }

    /**
     * Solo se paga el periodo vigente y una sola vez. Va aquí y no en el
     * controlador para que el error salga junto al botón, como los demás.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                /** @var UnitPolicy $policy */
                $policy = $this->route('policy');
                $payment = $this->route('payment');

                if ($policy->unit->currentPolicy?->isNot($policy)) {
                    $validator->errors()->add('receipt', 'Este periodo ya se renovó: solo se registran pagos del periodo vigente.');
                } elseif ($policy->isPaid($payment)) {
                    $validator->errors()->add('receipt', 'Ese pago ya está registrado.');
                } elseif ($payment === 'second' && ! $policy->second_payment_ends_on) {
                    $validator->errors()->add('receipt', 'El segundo pago no tiene fecha límite: captúrala antes de registrarlo.');
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
            'new_policy' => 'póliza nueva',
            'new_certificate' => 'certificado nuevo',
            'new_first_payment_amount' => 'importe del primer pago',
            'new_second_payment_amount' => 'importe del segundo pago',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'receipt.mimes' => 'El comprobante debe ser PDF o imagen.',
            'receipt.max' => 'El comprobante puede pesar hasta 10 MB.',
            'paid_at.before_or_equal' => 'La fecha de pago no puede ser futura.',
            'new_policy.unique' => 'Ya hay un periodo con esa póliza.',
        ];
    }
}
