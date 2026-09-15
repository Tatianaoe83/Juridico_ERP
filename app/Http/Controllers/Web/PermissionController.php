<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Support\PermissionCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Catálogo de permisos: consulta, alta y edición.
 *
 * Aquí no se reparten: qué permisos concede cada rol se decide en /roles.
 */
class PermissionController extends Controller
{
    public function index(): Response
    {
        $permissions = Permission::with('roles:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (Permission $permission) => $this->summary($permission));

        return Inertia::render('Permissions/Index', [
            'permissions' => $permissions,
        ]);
    }

    /** POST /permisos */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', Rule::unique('permissions', 'name')],
        ], $this->messages());

        $permission = Permission::create(['name' => $data['name'], 'guard_name' => 'web']);

        $this->forgetCache();

        return to_route('permissions.index')->with('success', "Se creó el permiso {$permission->name}.");
    }

    /** PATCH /permisos/{permission} */
    public function update(Request $request, Permission $permission): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', Rule::unique('permissions', 'name')->ignore($permission)],
        ], $this->messages());

        $permission->update(['name' => $data['name']]);

        $this->forgetCache();

        return to_route('permissions.index')->with('success', "Se actualizó el permiso {$permission->name}.");
    }

    /** DELETE /permisos/{permission} — Spatie lo quita también de los roles que lo tenían. */
    public function destroy(Permission $permission): RedirectResponse
    {
        $name = $permission->name;
        $permission->delete();

        $this->forgetCache();

        return to_route('permissions.index')->with('success', "Se eliminó el permiso {$name}.");
    }

    /** @return array<string, mixed> */
    private function summary(Permission $permission): array
    {
        return [
            'id' => $permission->id,
            'name' => $permission->name,
            'label' => PermissionCatalog::label($permission->name),
            'area' => PermissionCatalog::area($permission->name),
            'roles' => $permission->roles
                ->sortBy(fn ($role) => [RoleController::rank($role->name), $role->name])
                ->pluck('name')
                ->values(),
            'created_at' => $permission->created_at?->toIso8601String(),
        ];
    }

    private function messages(): array
    {
        return [
            'name.required' => 'Ponle un nombre al permiso.',
            'name.unique' => 'Ya existe un permiso con ese nombre.',
        ];
    }

    /** Spatie cachea el mapa de permisos; sin esto el cambio tarda en verse. */
    private function forgetCache(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
