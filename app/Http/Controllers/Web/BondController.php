<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Policy\SaveBondRequest;
use App\Models\Bond;
use App\Models\BusinessUnit;
use App\Services\BondCalendar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Fianzas, dentro de Pólizas y Fianzas: el detalle, guardar y eliminar. La
 * tabla, el alta y la edición son las mismas páginas que las de las pólizas.
 */
class BondController extends Controller
{
    public function __construct(private readonly BondCalendar $calendar) {}

    /** GET /polizas/fianzas/{bond} */
    public function show(Bond $bond): Response
    {
        $bond->load(['creator:id,name', 'businessUnit:id,name']);

        return Inertia::render('Policies/BondShow', [
            'bond' => [
                ...$this->values($bond),
                'id' => $bond->id,
                'business_unit' => $bond->businessUnit?->name,
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

        // Sin fin de vigencia no hay nada que agendar ni que avisar.
        $note = $bond->valid_until ? $this->calendarNote($this->calendar->sync($bond, $request->user())) : '';

        return to_route('policies.bonds.show', $bond)->with('success', "Se registró la fianza {$bond->bond}.".$note);
    }

    /** GET /polizas/fianzas/{bond}/editar — la misma página de edición que las pólizas. */
    public function edit(Bond $bond): Response
    {
        return Inertia::render('Policies/Edit', [
            'type' => 'bond',
            'businessUnits' => BusinessUnit::orderBy('name')->get(['id', 'name']),
            'categories' => Bond::CATEGORIES,
            'bond' => [...$this->values($bond), 'id' => $bond->id],
        ]);
    }

    /** PATCH /polizas/fianzas/{bond} */
    public function update(SaveBondRequest $request, Bond $bond): RedirectResponse
    {
        $bond->update($request->validated());

        // Outlook solo se toca si se movió el fin de vigencia o aún no tiene
        // evento: actualizarlo le vuelve a llegar el aviso a los compartidos.
        // Si se borró la fecha, el evento se quita sin decir nada.
        $note = '';

        if (! $bond->valid_until) {
            $this->calendar->forget($bond, $request->user());
        } elseif ($bond->wasChanged('valid_until') || ! $bond->calendar_event_id) {
            $note = $this->calendarNote($this->calendar->sync($bond, $request->user()));
        }

        return to_route('policies.bonds.show', $bond)->with('success', "Se actualizó la fianza {$bond->bond}.".$note);
    }

    /** DELETE /polizas/fianzas/{bond} */
    public function destroy(Request $request, Bond $bond): RedirectResponse
    {
        // Primero el evento: después del delete ya no hay de dónde sacar su id.
        $this->calendar->forget($bond, $request->user());

        $bond->delete();

        return to_route('policies.index')->with('success', "Se eliminó la fianza {$bond->bond}.");
    }

    /**
     * Agendar es un extra: si Outlook no respondió o la cuenta no está
     * vinculada, se dice en el mismo aviso en vez de fallar el guardado.
     */
    private function calendarNote(bool $agendada): string
    {
        return $agendada
            ? ' Se agendó en el calendario.'
            : ' No se pudo agendar en el calendario: revisa la conexión con Outlook.';
    }

    /**
     * Los campos tal como se capturan: el detalle y el formulario leen lo mismo.
     *
     * @return array<string, mixed>
     */
    private function values(Bond $bond): array
    {
        return [
            'business_unit_id' => $bond->business_unit_id,
            'bond' => $bond->bond,
            'beneficiary' => $bond->beneficiary,
            'bonding_company' => $bond->bonding_company,
            'amount' => $bond->amount !== null ? (float) $bond->amount : null,
            'requested_on' => $bond->requested_on?->toDateString(),
            'issued_on' => $bond->issued_on?->toDateString(),
            'valid_from' => $bond->valid_from?->toDateString(),
            'valid_until' => $bond->valid_until?->toDateString(),
            'source_document' => $bond->source_document,
            'category' => $bond->category,
            'related' => $bond->related,
            'cancellation_requested_on' => $bond->cancellation_requested_on?->toDateString(),
            'cancelled_on' => $bond->cancelled_on?->toDateString(),
            'comments' => $bond->comments,
        ];
    }
}
