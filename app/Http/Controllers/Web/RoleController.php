<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Support\PermissionCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Alta, consulta, edición y baja de roles, con los permisos que concede cada
 * uno. Los permisos no se crean aquí: solo se eligen de los que existen.
 *
 * Todo cuelga de `can:roles.manage` en las rutas: redefinir qué puede un rol
 * es lo más sensible de la app, quien lo controla puede concederse lo demás.
 */
class RoleController extends Controller
{
    /** De más a menos privilegio; lo que no esté aquí va al final. */
    public const ORDER = ['superadmin', 'admin', 'user'];

    /**
     * Roles de los que depende el código por nombre (el Gate, el seeder, el
     * alta de usuarios): renombrarlos o borrarlos rompería la app.
     */
    private const SYSTEM = ['superadmin', 'admin', 'user'];

    /** Ordena en PHP y no con FIELD(): esa función es de MySQL y los tests usan SQLite. */
    public static function rank(string $role): int
    {
        $position = array_search($role, self::ORDER, true);

        return $position === false ? PHP_INT_MAX : $position;
    }

    public function index(): Response
    {
        $roles = Role::withCount(['users', 'permissions'])
            ->get()
            ->sortBy(fn (Role $role) => [self::rank($role->name), $role->name])
            ->values()
            ->map(fn (Role $role) => $this->summary($role));

        return Inertia::render('Roles/Index', [
            'roles' => $roles,
            'totalPermissions' => Permission::count(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Roles/Form', [
            'role' => null,
            'groups' => PermissionCatalog::groups(),
        ]);
    }

    /** POST /roles */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', Rule::unique('roles', 'name')],
            ...$this->permissionRules(),
        ], $this->messages());

        $role = DB::transaction(function () use ($data) {
            $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);
            $role->syncPermissions($data['permissions'] ?? []);

            return $role;
        });

        $this->forgetCache();

        return to_route('roles.index')->with('success', "Se creó el rol {$role->name}.");
    }

    public function show(Role $role): Response
    {
        $role->loadCount(['users', 'permissions'])->load('permissions:id,name');

        return Inertia::render('Roles/Show', [
            'role' => [
                ...$this->summary($role),
                'permissions' => $role->permissions->pluck('name'),
                // Una muestra basta para saber a quién afecta un cambio.
                'users' => $role->users()->orderBy('name')->limit(8)->get(['users.id', 'name', 'email']),
            ],
            'groups' => PermissionCatalog::groups(),
        ]);
    }

    public function edit(Role $role): Response
    {
        abort_if($this->unrestricted($role), 403, 'El superadmin no se edita: no usa permisos.');

        $role->loadCount(['users', 'permissions'])->load('permissions:id,name');

        return Inertia::render('Roles/Form', [
            'role' => [
                ...$this->summary($role),
                'permissions' => $role->permissions->pluck('name'),
            ],
            'groups' => PermissionCatalog::groups(),
        ]);
    }

    /** PATCH /roles/{role} */
    public function update(Request $request, Role $role): RedirectResponse
    {
        abort_if(
            $this->unrestricted($role),
            422,
            'El superadmin no usa permisos: pasa por el Gate y marcárselos no cambiaría nada.',
        );

        $system = in_array($role->name, self::SYSTEM, true);

        $data = $request->validate([
            // El nombre de un rol del sistema no viaja: el código lo busca por nombre.
            'name' => $system
                ? ['prohibited']
                : ['required', Rule::unique('roles', 'name')->ignore($role)],
            ...$this->permissionRules(),
        ], $this->messages());

        DB::transaction(function () use ($role, $data, $system) {
            if (! $system) {
                $role->update(['name' => $data['name']]);
            }

            $role->syncPermissions($data['permissions'] ?? []);
        });

        $this->forgetCache();

        return to_route('roles.index')->with('success', "Se actualizó el rol {$role->name}.");
    }

    /** DELETE /roles/{role} */
    public function destroy(Role $role): RedirectResponse
    {
        abort_if(in_array($role->name, self::SYSTEM, true), 422, 'Los roles del sistema no se pueden eliminar.');

        // Borrarlo dejaría a esas personas sin rol y sin acceso de golpe.
        $users = $role->users()->count();
        abort_if($users > 0, 422, "Hay {$users} usuario(s) con este rol: reasígnalos antes de eliminarlo.");

        $name = $role->name;
        $role->delete();

        $this->forgetCache();

        return to_route('roles.index')->with('success', "Se eliminó el rol {$name}.");
    }

    /** @return array<string, mixed> */
    private function summary(Role $role): array
    {
        $system = in_array($role->name, self::SYSTEM, true);

        return [
            'id' => $role->id,
            'name' => $role->name,
            'users_count' => $role->users_count,
            'permissions_count' => $role->permissions_count,
            // El superadmin se resuelve en el Gate, no con permisos.
            'unrestricted' => $this->unrestricted($role),
            'system' => $system,
            'can' => [
                'update' => ! $this->unrestricted($role),
                'delete' => ! $system && $role->users_count === 0,
            ],
            'created_at' => $role->created_at?->toIso8601String(),
        ];
    }

    private function unrestricted(Role $role): bool
    {
        return $role->name === 'superadmin';
    }

    /** Al menos uno (un rol sin permisos no sirve) y solo de los que ya existen. */
    private function permissionRules(): array
    {
        return [
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['string', 'distinct', Rule::exists('permissions', 'name')->where('guard_name', 'web')],
        ];
    }

    private function messages(): array
    {
        return [
            'name.required' => 'Ponle un nombre al rol.',
            'name.unique' => 'Ya existe un rol con ese nombre.',
            'permissions.required' => 'Marca al menos un permiso.',
            'permissions.min' => 'Marca al menos un permiso.',
            'name.prohibited' => 'El nombre de un rol del sistema no se puede cambiar.',
        ];
    }

    /** Spatie cachea el mapa de permisos; sin esto el cambio tarda en verse. */
    private function forgetCache(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
