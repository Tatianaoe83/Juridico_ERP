<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        /*
         * El superadmin pasa cualquier comprobación sin tener permisos
         * asignados. Es lo que evita que un permiso nuevo lo deje fuera hasta
         * que alguien se acuerde de marcárselo.
         *
         * Devolver null y no false es esencial: false cortaría la evaluación y
         * ninguna Policy llegaría a ejecutarse para el resto de los roles.
         */
        Gate::before(fn (User $user) => $user->hasRole('superadmin') ? true : null);
    }
}
