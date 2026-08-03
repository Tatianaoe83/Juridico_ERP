<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Matriz de roles × permisos.
 *
 * Es la única pantalla que redefine qué puede hacer un rol; el resto de la
 * app solo asigna roles ya existentes a personas.
 */
class PermissionController extends Controller
{
    /** Etiquetas de las áreas, deducidas del prefijo del permiso. */
    private const AREAS = [
        'users' => 'Usuarios',
        'roles' => 'Roles',
        'calendar' => 'Calendario',
        'events' => 'Eventos',
    ];

    public function index(): Response
    {
        // Mismo orden que la pantalla de roles, resuelto en PHP: FIELD() es de
        // MySQL y reventaría en cualquier otro motor.
        $roles = Role::with('permissions:id,name')
            ->get()
            ->sortBy(fn (Role $role) => RoleController::rank($role->name))
            ->values();

        $groups = Permission::orderBy('name')
            ->get()
            ->groupBy(fn (Permission $permission) => strtok($permission->name, '.'))
            ->map(fn ($permissions, $prefix) => [
                'area' => self::AREAS[$prefix] ?? ucfirst($prefix),
                'permissions' => $permissions->pluck('name')->values(),
            ])
            ->values();

        return Inertia::render('Permissions/Index', [
            'groups' => $groups,
            'roles' => $roles->map(fn (Role $role) => [
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name'),
                // Sin casillas: pasa todo por Gate::before. Marcárselas sería
                // peor, porque un permiso nuevo lo dejaría fuera.
                'unrestricted' => $role->name === 'superadmin',
            ]),
        ]);
    }

    /** PATCH /permisos — concede o revoca un permiso a un rol. */
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'role' => ['required', 'string', Rule::exists('roles', 'name')],
            'permission' => ['required', 'string', Rule::exists('permissions', 'name')],
            'granted' => ['required', 'boolean'],
        ]);

        $role = Role::findByName($data['role']);

        abort_if(
            $role->name === 'superadmin',
            422,
            'El superadmin no usa permisos: pasa por el Gate y marcárselos no cambiaría nada.',
        );

        $data['granted']
            ? $role->givePermissionTo($data['permission'])
            : $role->revokePermissionTo($data['permission']);

        // Spatie cachea el mapa de permisos; sin esto el cambio tarda en verse.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $verb = $data['granted'] ? 'concedido a' : 'retirado de';

        return back()->with('success', "{$data['permission']} {$verb} {$data['role']}.");
    }
}
