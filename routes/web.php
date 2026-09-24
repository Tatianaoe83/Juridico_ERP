<?php

use App\Http\Controllers\Web\Auth\MicrosoftController;
use App\Http\Controllers\Web\CalendarController;
use App\Http\Controllers\Web\CalendarEventController;
use App\Http\Controllers\Web\CalendarShareController;
use App\Http\Controllers\Web\EventController;
use App\Http\Controllers\Web\FleetController;
use App\Http\Controllers\Web\LicenseController;
use App\Http\Controllers\Web\LicenseNotificationController;
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
    // En modo aplicación la agenda es una sola y compartida: quien tenga
    // events.update puede editar cualquier evento del buzón general.
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
        Route::delete('/calendario/compartir', [CalendarShareController::class, 'destroy'])
            ->name('calendar.unshare');
    });

    // Cumplimiento
    Route::get('/licencias', [LicenseController::class, 'index'])
        ->middleware('can:licencias.view')
        ->name('licenses.index');
    Route::post('/licencias', [LicenseController::class, 'store'])
        ->middleware('can:licencias.create')
        ->name('licenses.store');
    Route::patch('/licencias/{license}', [LicenseController::class, 'update'])
        ->middleware('can:licencias.update')
        ->name('licenses.update');
    Route::delete('/licencias/{license}', [LicenseController::class, 'destroy'])
        ->middleware('can:licencias.delete')
        ->name('licenses.destroy');
    Route::middleware('can:licencias.update')->group(function () {
        Route::put('/licencias/{license}/recordatorio', [LicenseNotificationController::class, 'update'])->name('licenses.reminder.update');
        Route::delete('/licencias/{license}/recordatorio', [LicenseNotificationController::class, 'destroy'])->name('licenses.reminder.destroy');
    });
    Route::get('/flotillas', [FleetController::class, 'index'])
        ->middleware('can:flotillas.view')
        ->name('fleets.index');
    // El alta tiene vista propia: son demasiados campos para un modal.
    Route::get('/flotillas/crear', [FleetController::class, 'create'])
        ->middleware('can:flotillas.create')
        ->name('fleets.create');
    Route::post('/flotillas', [FleetController::class, 'store'])
        ->middleware('can:flotillas.create')
        ->name('fleets.store');
    // Va después de /flotillas/crear: si no, «crear» entraría como {unit}.
    Route::get('/flotillas/{unit}', [FleetController::class, 'show'])
        ->middleware('can:flotillas.view')
        ->name('fleets.show');
    Route::get('/flotillas/{unit}/evidencias/{evidence}', [FleetController::class, 'evidence'])
        ->middleware('can:flotillas.view')
        ->name('fleets.evidence');
    // Registrar un pago semestral con su comprobante; el segundo renueva. El
    // periodo se busca dentro de la unidad: uno ajeno da 404.
    Route::post('/flotillas/{unit}/periodos/{policy}/pagos/{payment}', [FleetController::class, 'pay'])
        ->middleware('can:flotillas.update')
        ->whereIn('payment', ['first', 'second'])
        ->scopeBindings()
        ->name('fleets.pay');
    Route::delete('/flotillas/{unit}', [FleetController::class, 'destroy'])
        ->middleware('can:flotillas.delete')
        ->name('fleets.destroy');
    Route::get('/flotillas/{unit}/editar', [FleetController::class, 'edit'])
        ->middleware('can:flotillas.update')
        ->name('fleets.edit');
    Route::patch('/flotillas/{unit}', [FleetController::class, 'update'])
        ->middleware('can:flotillas.update')
        ->name('fleets.update');

    // Administración de usuarios. La Policy afina por registro: quién puede
    // tocar a quién no lo resuelve un permiso suelto.
    Route::get('/usuarios', [UserController::class, 'index'])->name('users.index');
    Route::post('/usuarios', [UserController::class, 'store'])->name('users.store');
    Route::patch('/usuarios/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('/usuarios/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    Route::patch('/usuarios/{user}/rol', [UserController::class, 'updateRole'])
        ->middleware('can:roles.manage')
        ->name('users.role');

    // Roles y permisos. Redefinir qué puede un rol es lo más sensible de la
    // app: quien lo controla puede concederse cualquier otra cosa.
    Route::middleware('can:roles.manage')->group(function () {
        Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
        Route::get('/roles/crear', [RoleController::class, 'create'])->name('roles.create');
        Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
        Route::get('/roles/{role}', [RoleController::class, 'show'])->name('roles.show');
        Route::get('/roles/{role}/editar', [RoleController::class, 'edit'])->name('roles.edit');
        Route::patch('/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
        Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
        Route::get('/permisos', [PermissionController::class, 'index'])->name('permissions.index');
        Route::post('/permisos', [PermissionController::class, 'store'])->name('permissions.store');
        Route::patch('/permisos/{permission}', [PermissionController::class, 'update'])->name('permissions.update');
        Route::delete('/permisos/{permission}', [PermissionController::class, 'destroy'])->name('permissions.destroy');
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
