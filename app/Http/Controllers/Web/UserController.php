<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

/**
 * Administración de usuarios desde la interfaz.
 *
 * Alta, edición y baja reutilizan los FormRequest, la Policy y el servicio de
 * la API v1: las reglas viven en un solo sitio y las dos entradas no pueden
 * divergir.
 */
class UserController extends Controller
{
    public function __construct(private readonly UserService $users) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $actor = $request->user();
        $people = $this->users->paginate($request->only('search', 'per_page'));

        return Inertia::render('Users/Index', [
            'users' => [
                'data' => collect($people->items())->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => $user->roles->pluck('name'),
                    'created_at' => $user->created_at?->toIso8601String(),
                    'updated_at' => $user->updated_at?->toIso8601String(),
                    // Quién es uno mismo: el front no debe ofrecer cambiarse el
                    // propio rol ni borrarse.
                    'is_self' => $user->is($actor),
                    // Resuelto por la Policy, que sabe de jerarquías: un admin
                    // ve a un superadmin pero no puede tocarlo.
                    'can' => [
                        'update' => $actor->can('update', $user),
                        'delete' => $actor->can('delete', $user) && ! $user->is($actor),
                    ],
                ]),
                'meta' => [
                    'total' => $people->total(),
                    'per_page' => $people->perPage(),
                    'current_page' => $people->currentPage(),
                    'last_page' => $people->lastPage(),
                ],
            ],
            'filters' => ['search' => $request->string('search')->value() ?: null],
            'roles' => Role::orderBy('name')->pluck('name'),
            // Cambiar roles es más sensible que editar un usuario: va aparte.
            'canManageRoles' => $actor->can('roles.manage'),
            'canCreate' => $actor->can('create', User::class),
        ]);
    }

    /** POST /usuarios */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = $this->users->create($this->withoutUnauthorizedRoles($request, $request->validated()));

        return back()->with('success', "Se creó la cuenta de {$user->name}.");
    }

    /** PATCH /usuarios/{user} */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->users->update($user, $this->withoutUnauthorizedRoles($request, $request->validated(), $user));

        return back()->with('success', "Se actualizaron los datos de {$user->name}.");
    }

    /** DELETE /usuarios/{user} */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        // El Gate::before del superadmin salta la Policy: la barandilla va aquí.
        abort_if($user->is($request->user()), 422, 'No puedes eliminar tu propia cuenta.');

        $name = $user->name;

        $this->users->delete($user);

        return back()->with('success', "Se eliminó la cuenta de {$name}.");
    }

    /**
     * Los FormRequest aceptan `roles`, pero repartir roles exige `roles.manage`
     * aparte de poder crear o editar: sin esto, un admin podría darse de alta
     * un superadmin. Y nadie se cambia el rol a sí mismo.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withoutUnauthorizedRoles(Request $request, array $data, ?User $target = null): array
    {
        $actor = $request->user();

        if (! $actor->can('roles.manage') || ($target && $target->is($actor))) {
            unset($data['roles']);
        }

        return $data;
    }

    /** PATCH /usuarios/{user}/rol */
    public function updateRole(Request $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $data = $request->validate([
            'role' => ['required', 'string', Rule::exists('roles', 'name')],
        ]);

        /*
         * Cambiarse el rol a uno mismo permitiría a un superadmin degradarse y
         * dejar al sistema sin quien administre. Como el Gate::before del
         * superadmin salta las Policies, la barandilla va aquí.
         */
        abort_if($user->is($request->user()), 422, 'No puedes cambiar tu propio rol.');

        $user->syncRoles($data['role']);

        return back()->with('success', "{$user->name} ahora es {$data['role']}.");
    }
}
