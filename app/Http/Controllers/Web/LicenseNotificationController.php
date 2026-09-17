<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\License;
use App\Services\LicenseCalendar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Aviso de vencimiento de una licencia (la campana de la tabla). Uno por
 * licencia: guardar lo crea o lo reemplaza.
 *
 * Se elige una sola cosa, cuándo avisar, con los mismos minutos que entiende
 * Outlook: viajan al evento y es Outlook quien lanza la alerta.
 */
class LicenseNotificationController extends Controller
{
    public function __construct(private readonly LicenseCalendar $calendar) {}

    /** PUT /licencias/{license}/recordatorio */
    public function update(Request $request, License $license): RedirectResponse
    {
        $data = $request->validate([
            'minutes_before' => ['required', 'integer', Rule::in(License::REMINDER_MINUTES)],
        ], [
            'minutes_before.required' => 'Elige cuándo avisar.',
            'minutes_before.in' => 'Ese aviso no está en la lista.',
        ]);

        $license->notification()->updateOrCreate([], $data);

        // La alerta viaja en el evento, así que hay que reescribirlo.
        $this->calendar->sync($license->load('notification'), $request->user());

        return to_route('licenses.index')->with('success', "Aviso guardado para {$license->name}.");
    }

    /** DELETE /licencias/{license}/recordatorio */
    public function destroy(Request $request, License $license): RedirectResponse
    {
        $license->notification()->delete();

        // El evento se queda, pero sin la alerta de Outlook.
        $this->calendar->sync($license->load('notification'), $request->user());

        return to_route('licenses.index')->with('success', "Se quitó el aviso de {$license->name}.");
    }
}
