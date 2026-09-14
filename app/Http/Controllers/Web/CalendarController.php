<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\MicrosoftAccount;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CalendarController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $mode = config('services.microsoft.mode', 'delegated');
        $calendar = $request->user()->calendarOwner();

        // En modo aplicación es el buzón general, sin vinculación de por medio.
        // En delegado, entrar por SSO vincula la cuenta pero no concede
        // Calendars.Read: sin ese permiso la agenda cuenta como no conectada.
        $connection = $calendar?->canReadCalendar() ? [
            'email' => $calendar->mailboxEmail(),
            'display_name' => $calendar instanceof MicrosoftAccount ? $calendar->display_name : null,
            'synced_at' => $calendar instanceof MicrosoftAccount ? $calendar->synced_at?->toIso8601String() : null,
        ] : null;

        return Inertia::render('Calendar/Index', [
            'mode' => $mode,
            'connection' => $connection,
            'timezone' => config('app.timezone'),
        ]);
    }
}
