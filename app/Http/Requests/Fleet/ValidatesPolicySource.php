<?php

namespace App\Http\Requests\Fleet;

use App\Models\UnitPolicy;
use Illuminate\Validation\Rule;

/**
 * El origen de la póliza, igual en automóviles y en maquinaria. La
 * empresarial no lleva nada más; la del proveedor es la que tiene beneficiario
 * preferente (en pantalla se elige así) y lleva quién es: quien vende,
 * financia o arrenda la unidad.
 */
trait ValidatesPolicySource
{
    /**
     * El proveedor se guarda siempre en mayúsculas.
     *
     * @return array<string, string>
     */
    private function uppercaseSource(): array
    {
        return is_string($this->input('supplier')) ? ['supplier' => mb_strtoupper($this->input('supplier'))] : [];
    }

    /** Si lo que llega es una póliza del proveedor. */
    public function fromSupplier(): bool
    {
        return $this->input('source') === UnitPolicy::SUPPLIER;
    }

    /**
     * Los datos del proveedor se ignoran en la empresarial: si se cambió de
     * proveedor a empresarial, lo que traiga el formulario no se valida.
     *
     * @return array<string, mixed>
     */
    private function sourceRules(): array
    {
        return [
            'source' => ['required', Rule::in(UnitPolicy::SOURCES)],
            'supplier' => ['exclude_unless:source,'.UnitPolicy::SUPPLIER, 'required', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    private function sourceAttributes(): array
    {
        return [
            'source' => 'beneficiario preferente',
            'supplier' => 'beneficiario preferente',
        ];
    }

    /** @return array<string, string> */
    private function sourceMessages(): array
    {
        return [
            'supplier.required' => 'Indica quién es el beneficiario preferente: la agencia, financiera o arrendadora.',
        ];
    }

    /**
     * Lo que se guarda del origen. En la empresarial los datos del proveedor
     * quedan vacíos, aunque antes haya sido del proveedor.
     *
     * @return array<string, mixed>
     */
    public function policySource(): array
    {
        $supplier = $this->validated('source') === UnitPolicy::SUPPLIER;

        return [
            'source' => $this->validated('source'),
            'supplier' => $supplier ? $this->validated('supplier') : null,
        ];
    }
}
