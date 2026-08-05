<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * Catálogo de permisos, agrupado por área.
     *
     * El nombre va como `area.acción` a propósito: permite agrupar en la
     * interfaz sin una tabla aparte, y se lee igual en el código que en la
     * pantalla.
     */
    private const PERMISSIONS = [
        // Usuarios
        'users.view',
        'users.create',
        'users.update',
        'users.delete',
        'roles.manage',

        // Calendario
        'calendar.view',
        'calendar.link',
        'calendar.share',

        // Eventos
        'events.create',
        'events.update',
        'events.delete',
    ];

    /**
     * Qué puede cada rol.
     *
     * `superadmin` va vacío a propósito: lo resuelve el Gate::before de
     * AppServiceProvider. Si se le listaran los permisos uno por uno, el día
     * que se agregue uno nuevo dejaría de tenerlo sin que nadie lo note.
     */
    private const ROLES = [
        'superadmin' => [],

        'admin' => [
            'users.view',
            'users.create',
            'users.update',
            'calendar.view',
            'calendar.link',
            'calendar.share',
            'events.create',
            'events.update',
            'events.delete',
        ],

        'user' => [
            'calendar.view',
            'calendar.link',
            'events.create',
            'events.update',
            'events.delete',
        ],
    ];

    /**
     * Siembra los valores por defecto sin pisar lo que se haya configurado.
     *
     * `syncPermissions()` reemplazaba la lista completa, así que cada despliegue
     * revertía en silencio todo lo ajustado desde la matriz de /permisos. Aquí
     * solo se conceden permisos en dos casos:
     *
     *  - el rol acaba de nacer, así que no hay nada que respetar;
     *  - el permiso acaba de nacer, así que nadie ha decidido aún sobre él.
     *
     * Nunca se revoca: quitar un permiso es siempre una decisión de la interfaz.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $fresh = [];

        foreach (self::PERMISSIONS as $permission) {
            $model = Permission::findOrCreate($permission, 'web');

            if ($model->wasRecentlyCreated) {
                $fresh[] = $permission;
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::ROLES as $role => $permissions) {
            $model = Role::findOrCreate($role, 'web');

            $grant = $model->wasRecentlyCreated
                ? $permissions
                : array_intersect($permissions, $fresh);

            if ($grant) {
                $model->givePermissionTo($grant);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
