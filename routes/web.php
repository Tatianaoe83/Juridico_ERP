<?php

use App\Http\Controllers\Web\Auth\MicrosoftController;
use App\Http\Controllers\Web\CalendarController;
use App\Http\Controllers\Web\CalendarEventController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Portada: manda a la sección inicial o al login.
Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('calendar.index')
        : redirect()->route('login');
})->name('home');

// El callback atiende las dos entradas: alta por SSO (invitado) y
// consentimiento del calendario (con sesión). Por eso va sin middleware.
Route::get('/auth/microsoft/callback', [MicrosoftController::class, 'callback'])
    ->name('microsoft.callback');

Route::middleware('guest')->group(function () {
    Route::get('/auth/microsoft/login', [MicrosoftController::class, 'login'])
        ->name('microsoft.login');
});

Route::middleware('auth')->group(function () {
    // Punto de entrada tras iniciar sesión.
    Route::redirect('/dashboard', '/calendario')->name('dashboard');

    Route::get('/calendario', CalendarController::class)->name('calendar.index');
    Route::get('/calendario/eventos', CalendarEventController::class)->name('calendar.events');

    // Vinculación con Microsoft 365
    Route::get('/auth/microsoft/redirect', [MicrosoftController::class, 'redirect'])
        ->name('microsoft.redirect');
    Route::delete('/auth/microsoft', [MicrosoftController::class, 'destroy'])
        ->name('microsoft.destroy');
});

require __DIR__.'/auth.php';
