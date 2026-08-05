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
    /**
     * Intención de cada rol: para qué existe, no qué puede.
     *
     * Lo que puede se deriva de sus permisos reales en `summarize()`. Antes esta
     * constante describía capacidades concretas y quedaba mintiendo en cuanto
     * alguien tocaba la matriz de /permisos.
     */
    private const PURPOSE = [
        'superadmin' => 'Acceso total. No lleva permisos marcados: los salta desde el Gate, así que hereda cualquiera que se agregue después.',
        'admin' => 'Opera el sistema y administra las cuentas del departamento.',
        'user' => 'Cuenta de uso diario, centrada en su propia agenda.',
    ];

    /** Frase por área, en orden de gravedad. */
    private const CAPABILITIES = [
        'roles.manage' => 'reparte roles y permisos',
        'users.delete' => 'elimina cuentas',
        'users.create' => 'da de alta cuentas',
        'users.update' => 'edita cuentas',
        'users.view' => 'consulta el directorio',
        'calendar.share' => 'comparte calendarios',
        'calendar.link' => 'vincula su cuenta de Microsoft',
        'events.create' => 'crea eventos',
        'events.update' => 'edita eventos',
        'events.delete' => 'borra eventos',
        'calendar.view' => 'consulta el calendario',
    ];

    /**
     * Resumen legible de lo que un rol puede hacer hoy, según sus permisos.
     *
     * @param  \Illuminate\Support\Collection<int, string>  $permissions
     */
    private static function summarize($permissions): ?string
    {
        $phrases = collect(self::CAPABILITIES)
            ->filter(fn ($phrase, $permission) => $permissions->contains($permission))
            ->values();

        if ($phrases->isEmpty()) {
            return null;
        }

        // Las tres más graves bastan para dar la idea; el detalle está en la
        // lista de permisos que la propia tarjeta ya muestra.
        $top = $phrases->take(3);

        return ucfirst($top->join(', ', ' y ')).($phrases->count() > 3 ? ', entre otros.' : '.');
    }

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
                'description' => self::PURPOSE[$role->name] ?? null,
                // El superadmin no tiene permisos que resumir: los salta.
                'summary' => $role->name === 'superadmin'
                    ? null
                    : self::summarize($role->permissions->pluck('name')),
                'users_count' => $role->users_count,
                'permissions_count' => $role->permissions_count,
                'permissions' => $role->permissions->pluck('name'),
                // El superadmin se resuelve en el Gate, no con permisos.
                'unrestricted' => $role->name === 'superadmin',
            ]);

        return Inertia::render('Roles/Index', ['roles' => $roles]);
    }
}
