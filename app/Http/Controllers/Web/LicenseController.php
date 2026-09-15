<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Licencias y permisos (Cumplimiento). Por ahora solo la página vacía.
 */
class LicenseController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Licenses/Index');
    }
}
