<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CalendarController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $mode = config('services.microsoft.mode', 'delegated');
        $account = $request->user()->microsoftAccount;

        // En modo aplicación no hay vinculación: el buzón se resuelve por correo.
        $connection = $mode === 'application'
            ? ['email' => $request->user()->email, 'display_name' => null, 'synced_at' => null]
            // Entrar por SSO vincula la cuenta pero no concede Calendars.Read:
            // sin ese permiso la agenda cuenta como no conectada.
            : ($account?->canReadCalendar() ? [
                'email' => $account->email,
                'display_name' => $account->display_name,
                'synced_at' => $account->synced_at?->toIso8601String(),
            ] : null);

        return Inertia::render('Calendar/Index', [
            'mode' => $mode,
            'connection' => $connection,
            'timezone' => config('app.timezone'),
        ]);
    }
}
