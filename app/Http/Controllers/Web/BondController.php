<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Policy\SaveBondRequest;
use App\Models\Bond;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Fianzas, dentro de Pólizas y Fianzas: el detalle, guardar y eliminar. La
 * tabla, el alta y la edición son las mismas páginas que las de las pólizas.
 */
class BondController extends Controller
{
    /** GET /polizas/fianzas/{bond} */
    public function show(Bond $bond): Response
    {
        $bond->load('creator:id,name');

        return Inertia::render('Policies/BondShow', [
            'bond' => [
                ...$this->values($bond),
                'id' => $bond->id,
                'status' => $bond->status(),
                'created_by' => $bond->creator?->name,
                'created_at' => $bond->created_at?->toIso8601String(),
            ],
        ]);
    }

    /** POST /polizas/fianzas — el formulario está en la misma alta de pólizas (PolicyController@create). */
    public function store(SaveBondRequest $request): RedirectResponse
    {
        $bond = Bond::create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        return to_route('policies.bonds.show', $bond)->with('success', "Se registró la fianza {$bond->bond}.");
    }

    /** GET /polizas/fianzas/{bond}/editar — la misma página de edición que las pólizas. */
    public function edit(Bond $bond): Response
    {
        return Inertia::render('Policies/Edit', [
            'type' => 'bond',
            'bond' => [...$this->values($bond), 'id' => $bond->id],
        ]);
    }

    /** PATCH /polizas/fianzas/{bond} */
    public function update(SaveBondRequest $request, Bond $bond): RedirectResponse
    {
        $bond->update($request->validated());

        return to_route('policies.bonds.show', $bond)->with('success', "Se actualizó la fianza {$bond->bond}.");
    }

    /** DELETE /polizas/fianzas/{bond} */
    public function destroy(Bond $bond): RedirectResponse
    {
        $bond->delete();

        return to_route('policies.index')->with('success', "Se eliminó la fianza {$bond->bond}.");
    }

    /**
     * Los campos tal como se capturan: el detalle y el formulario leen lo mismo.
     *
     * @return array<string, mixed>
     */
    private function values(Bond $bond): array
    {
        return [
            'bond' => $bond->bond,
            'beneficiary' => $bond->beneficiary,
            'bonding_company' => $bond->bonding_company,
            'amount' => $bond->amount !== null ? (float) $bond->amount : null,
            'requested_on' => $bond->requested_on?->toDateString(),
            'issued_on' => $bond->issued_on?->toDateString(),
            'valid_from' => $bond->valid_from?->toDateString(),
            'valid_until' => $bond->valid_until?->toDateString(),
            'source_document' => $bond->source_document,
            'product' => $bond->product,
            'related' => $bond->related,
            'cancellation_requested_on' => $bond->cancellation_requested_on?->toDateString(),
            'cancelled_on' => $bond->cancelled_on?->toDateString(),
            'comments' => $bond->comments,
        ];
    }
}
