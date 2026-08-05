<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Calendar\CalendarAccess;
use App\Services\Calendar\CalendarView;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CalendarController extends Controller
{
    public function __construct(private readonly CalendarAccess $access) {}

    public function __invoke(Request $request): Response
    {
        $mode = config('services.microsoft.mode', 'delegated');

        // En modo aplicación no hay vinculación: el buzón se resuelve por correo.
        if ($mode === 'application') {
            return Inertia::render('Calendar/Index', [
                'mode' => $mode,
                'connection' => ['email' => $request->user()->email, 'display_name' => null, 'synced_at' => null],
                'calendars' => [],
                'timezone' => config('app.timezone'),
            ]);
        }

        /*
         * El propio si vinculó, o el que alguien más le compartió. Antes esto
         * era `$user->microsoftAccount` a secas, así que a quien le compartían
         * un calendario le seguía apareciendo «conecta tu cuenta».
         */
        $available = $this->access->available($request->user());
        $current = $this->access->resolve($request->user(), $request->integer('calendario') ?: null);

        return Inertia::render('Calendar/Index', [
            'mode' => $mode,
            'connection' => $current?->toArray(),
            // Para el selector: solo tiene sentido si hay más de uno.
            'calendars' => $available->map(fn (CalendarView $view) => [
                'owner_id' => $view->ownerId,
                'owner_name' => $view->ownerName,
                'own' => $view->own(),
            ])->all(),
            'timezone' => config('app.timezone'),
        ]);
    }
}
