<?php

use App\Http\Controllers\Web\Auth\AuthenticatedSessionController;
use Illuminate\Support\Facades\Route;

// Sin registro: las cuentas las da de alta un administrador.
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
