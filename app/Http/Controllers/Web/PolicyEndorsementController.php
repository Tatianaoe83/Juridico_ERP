<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Policy\StorePolicyEndorsementRequest;
use App\Models\ConstructionPolicy;
use App\Models\ConstructionPolicyEndorsement;
use App\Services\ConstructionPolicyCalendar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Endosos de extensión de las pólizas de obra: la misma póliza, con el mismo
 * número, recorre su fin de vigencia. Se registran y se consultan desde el
 * detalle de la póliza.
 */
class PolicyEndorsementController extends Controller
{
    public function __construct(private readonly ConstructionPolicyCalendar $calendar) {}

    /**
     * POST /polizas/{policy}/endosos
     *
     * Guarda el endoso con el fin que tenía la póliza, le pone el nuevo y
     * recorre su evento de Outlook.
     */
    public function store(StorePolicyEndorsementRequest $request, ConstructionPolicy $policy): RedirectResponse
    {
        $file = $request->file('document');

        DB::transaction(function () use ($request, $policy, $file) {
            $policy->endorsements()->create([
                'endorsement' => $request->validated('endorsement'),
                'previous_valid_until' => $policy->valid_until,
                'valid_until' => $request->validated('valid_until'),
                'amount' => $request->validated('amount'),
                'path' => $file ? Storage::disk('local')->putFile("policies/{$policy->id}", $file) : null,
                'name' => $file?->getClientOriginalName(),
                'mime_type' => $file?->getClientMimeType(),
                'size' => $file?->getSize(),
                'created_by' => $request->user()->id,
            ]);

            $policy->update(['valid_until' => $request->validated('valid_until')]);
        });

        $note = PolicyController::calendarNote($this->calendar->sync($policy, $request->user()));

        return back()->with('success', "Se registró el endoso de la póliza {$policy->policy}.".$note);
    }

    /**
     * GET /polizas/{policy}/endosos/{endorsement}/archivo
     *
     * El documento vive fuera de public: se sirve por aquí, con el nombre con
     * el que se subió y solo si es de esta póliza.
     */
    public function file(ConstructionPolicy $policy, ConstructionPolicyEndorsement $endorsement): StreamedResponse
    {
        abort_unless($endorsement->construction_policy_id === $policy->id, 404);
        abort_unless($endorsement->path && Storage::disk('local')->exists($endorsement->path), 404);

        return Storage::disk('local')->download($endorsement->path, $endorsement->name);
    }

    /**
     * DELETE /polizas/{policy}/endosos/{endorsement}
     *
     * Solo el último: la póliza regresa al fin que tenía antes de él. Quitar
     * uno de en medio dejaría la cadena de fechas sin sentido.
     */
    public function destroy(Request $request, ConstructionPolicy $policy, ConstructionPolicyEndorsement $endorsement): RedirectResponse
    {
        abort_unless($endorsement->construction_policy_id === $policy->id, 404);

        if ($policy->endorsements()->get()->last()?->isNot($endorsement)) {
            return back()->with('error', 'Solo se puede quitar el último endoso.');
        }

        DB::transaction(function () use ($policy, $endorsement) {
            $policy->update(['valid_until' => $endorsement->previous_valid_until]);

            $endorsement->delete();
        });

        if ($endorsement->path) {
            Storage::disk('local')->delete($endorsement->path);
        }

        $note = PolicyController::calendarNote($this->calendar->sync($policy, $request->user()));

        return back()->with('success', "Se quitó el endoso de la póliza {$policy->policy}.".$note);
    }
}
