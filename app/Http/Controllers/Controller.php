<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    /**
     * Desde Laravel 11 el controlador base viene vacío y el trait hay que
     * añadirlo a mano. Es lo que habilita `authorize()` y `authorizeResource()`.
     */
    use AuthorizesRequests;
}
