<?php

use App\Http\Controllers\Web\Auth\AuthenticatedSessionController;
use Illuminate\Support\Facades\Route;

// No hay alta pública: las cuentas las crea TI o nacen del SSO de Microsoft.
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
