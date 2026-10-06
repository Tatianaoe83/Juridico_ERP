<?php

namespace App\Http\Requests\Policy;

use App\Rules\ValidDocumentContent;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Las facturas de una cuota: el PDF y el XML del CFDI. Se pueden subir antes
 * o después de pagar, porque la factura no siempre llega con el pago.
 */
class StorePolicyInvoiceRequest extends FormRequest
{
    public const INVOICE_TYPES = 'pdf,xml';

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
            'invoices' => ['required', 'array', 'min:1', 'max:4'],
            'invoices.*' => ['file', 'max:10240', 'extensions:'.self::INVOICE_TYPES, new ValidDocumentContent],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'invoices' => 'facturas',
            'invoices.*' => 'factura',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'invoices.max' => 'Se suben hasta 4 archivos a la vez.',
            'invoices.*.extensions' => 'La factura debe ser PDF o XML.',
            'invoices.*.max' => 'Cada archivo puede pesar hasta 10 MB.',
        ];
    }
}
