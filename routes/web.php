<?php

use App\Http\Controllers\Web\Auth\MicrosoftController;
use App\Http\Controllers\Web\CalendarController;
use App\Http\Controllers\Web\CalendarEventController;
use App\Http\Controllers\Web\CalendarShareController;
use App\Http\Controllers\Web\EventController;
use App\Http\Controllers\Web\PermissionController;
use App\Http\Controllers\Web\RoleController;
use App\Http\Controllers\Web\UserController;
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

    /*
     * Se usa `can:` y no el middleware de Spatie: `can` pasa por el Gate, que
     * es donde vive el atajo del superadmin. El middleware `permission:`
     * consulta los permisos directamente y lo dejaría fuera.
     */
    Route::get('/calendario', CalendarController::class)
        ->middleware('can:calendar.view')
        ->name('calendar.index');
    Route::get('/calendario/eventos', CalendarEventController::class)
        ->middleware('can:calendar.view')
        ->name('calendar.events');

    // Eventos: se crean, editan y borran directo en el calendario de Outlook.
    // El id de Graph viaja en el cuerpo, no en la URL: es base64url y trae / y +.
    // La página vive en /compartido; las mutaciones siguen en /eventos porque
    // son sobre eventos y se disparan también desde el calendario.
    //
    // No hace falta comprobar de quién es el evento: en modo delegado el token
    // es del propio usuario, así que Graph solo le deja tocar su calendario.
    Route::get('/compartido', [EventController::class, 'index'])
        ->middleware('can:calendar.view')
        ->name('shared.index');
    Route::post('/eventos', [EventController::class, 'store'])
        ->middleware('can:events.create')
        ->name('events.store');
    Route::patch('/eventos', [EventController::class, 'update'])
        ->middleware('can:events.update')
        ->name('events.update');
    Route::delete('/eventos', [EventController::class, 'destroy'])
        ->middleware('can:events.delete')
        ->name('events.destroy');

    // Con quién se comparte el calendario. La lista la mantiene Outlook.
    Route::middleware('can:calendar.share')->group(function () {
        Route::post('/calendario/compartir', [CalendarShareController::class, 'store'])
            ->name('calendar.share');
        Route::post('/calendario/compartir/reenviar', [CalendarShareController::class, 'resend'])
            ->name('calendar.share.resend');
        Route::delete('/calendario/compartir', [CalendarShareController::class, 'destroy'])
            ->name('calendar.unshare');
    });

    // Administración de usuarios. La Policy afina por registro: quién puede
    // tocar a quién no lo resuelve un permiso suelto.
    Route::get('/usuarios', [UserController::class, 'index'])->name('users.index');
    Route::post('/usuarios', [UserController::class, 'store'])
        ->middleware('can:users.create')
        ->name('users.store');
    Route::patch('/usuarios/{user}/rol', [UserController::class, 'updateRole'])
        ->middleware('can:roles.manage')
        ->name('users.role');

    /*
     * Ficha, edición y baja. No llevan `can:` en la ruta: quién puede tocar a
     * quién depende del registro (un admin no toca a un superadmin, nadie se
     * borra a sí mismo), y eso solo lo sabe la Policy con el usuario delante.
     */
    Route::get('/usuarios/{user}', [UserController::class, 'show'])->name('users.show');
    Route::get('/usuarios/{user}/editar', [UserController::class, 'edit'])->name('users.edit');
    Route::patch('/usuarios/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('/usuarios/{user}', [UserController::class, 'destroy'])->name('users.destroy');

    // Roles y permisos. Redefinir qué puede un rol es lo más sensible de la
    // app: quien lo controla puede concederse cualquier otra cosa.
    Route::middleware('can:roles.manage')->group(function () {
        Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
        Route::get('/permisos', [PermissionController::class, 'index'])->name('permissions.index');
        Route::patch('/permisos', [PermissionController::class, 'update'])->name('permissions.update');
    });

    // Vinculación con Microsoft 365
    Route::middleware('can:calendar.link')->group(function () {
        Route::get('/auth/microsoft/redirect', [MicrosoftController::class, 'redirect'])
            ->name('microsoft.redirect');
        Route::delete('/auth/microsoft', [MicrosoftController::class, 'destroy'])
            ->name('microsoft.destroy');
    });
});

require __DIR__.'/auth.php';
