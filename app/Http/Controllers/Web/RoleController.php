<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

/**
 * Panorama de los roles: qué son, cuánta gente los tiene y cuánto pueden.
 * Editar qué puede cada uno vive en PermissionController.
 */
class RoleController extends Controller
{
    /** Descripción de cada rol. No vive en la base: es texto de producto. */
    private const DESCRIPTIONS = [
        'superadmin' => 'Acceso total. No lleva permisos marcados: los salta desde el Gate, así que hereda cualquiera que se agregue después.',
        'admin' => 'Opera el sistema y administra cuentas, pero no puede borrarlas ni repartir roles.',
        'user' => 'Su propia agenda: consulta el calendario y gestiona sus eventos.',
    ];

    /** De más a menos privilegio; lo que no esté aquí va al final. */
    public const ORDER = ['superadmin', 'admin', 'user'];

    /** Ordena en PHP y no con FIELD(): esa función es de MySQL y los tests usan SQLite. */
    public static function rank(string $role): int
    {
        $position = array_search($role, self::ORDER, true);

        return $position === false ? PHP_INT_MAX : $position;
    }

    public function index(): Response
    {
        $roles = Role::withCount(['users', 'permissions'])
            ->with('permissions:id,name')
            ->get()
            ->sortBy(fn (Role $role) => self::rank($role->name))
            ->values()
            ->map(fn (Role $role) => [
                'name' => $role->name,
                'description' => self::DESCRIPTIONS[$role->name] ?? null,
                'users_count' => $role->users_count,
                'permissions_count' => $role->permissions_count,
                'permissions' => $role->permissions->pluck('name'),
                // El superadmin se resuelve en el Gate, no con permisos.
                'unrestricted' => $role->name === 'superadmin',
            ]);

        return Inertia::render('Roles/Index', ['roles' => $roles]);
    }
}
