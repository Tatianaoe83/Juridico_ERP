<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
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
 * Solo lista y asigna rol. El alta, la edición y la baja siguen viviendo en la
 * API v1, que ya está protegida por UserPolicy.
 */
class UserController extends Controller
{
    public function __construct(private readonly UserService $users) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $people = $this->users->paginate($request->only('search', 'per_page'));

        return Inertia::render('Users/Index', [
            'users' => [
                'data' => collect($people->items())->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => $user->roles->pluck('name'),
                    'created_at' => $user->created_at?->toIso8601String(),
                    // Quién es uno mismo: el front no debe ofrecer cambiarse el
                    // propio rol ni borrarse.
                    'is_self' => $user->is($request->user()),
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
            'canManageRoles' => $request->user()->can('roles.manage'),
        ]);
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
