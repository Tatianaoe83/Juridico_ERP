<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\User;
use App\Services\AuthService;
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
                    // Se resuelve por registro: la Policy impide tocar a quien
                    // está por encima, y eso varía de una fila a otra.
                    'can' => [
                        'update' => $request->user()->can('update', $user),
                        'delete' => $request->user()->can('delete', $user),
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
            'canManageRoles' => $request->user()->can('roles.manage'),
            'canCreateUsers' => $request->user()->can('create', User::class),
        ]);
    }

    /** POST /usuarios */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->safe()->only(['name', 'email', 'password']);

        /*
         * El rol no se toma tal cual del formulario: `users.create` solo
         * autoriza a dar de alta. Sin `roles.manage`, quien crea cuentas podría
         * nombrarse un superadmin de paso y saltarse todo el control.
         */
        $roles = $request->user()->can('roles.manage')
            ? array_filter($request->safe()->array('roles'))
            : [];

        // Una cuenta sin rol entra pero choca con un 403 en la primera pantalla:
        // todas las rutas van detrás de `can:`.
        $data['roles'] = $roles ?: [AuthService::DEFAULT_ROLE];

        $user = $this->users->create($data);

        return back()->with('success', "Cuenta creada para {$user->name}.");
    }

    /** GET /usuarios/{user} — ficha de solo lectura. */
    public function show(Request $request, User $user): Response
    {
        $this->authorize('view', $user);

        return Inertia::render('Users/Show', [
            'person' => $this->present($request, $user),
        ]);
    }

    /** GET /usuarios/{user}/editar */
    public function edit(Request $request, User $user): Response
    {
        $this->authorize('update', $user);

        return Inertia::render('Users/Edit', [
            'person' => $this->present($request, $user),
            'roles' => Role::orderBy('name')->pluck('name'),
            'canManageRoles' => $request->user()->can('roles.manage'),
        ]);
    }

    /** PATCH /usuarios/{user} */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->safe()->only(['name', 'email', 'password']);

        // Igual que en el alta: cambiar el rol es `roles.manage`, no `users.update`.
        if ($request->user()->can('roles.manage') && $request->safe()->has('roles')) {
            $roles = array_filter($request->safe()->array('roles'));

            /*
             * Cambiarse el rol a uno mismo permitiría a un superadmin degradarse
             * y dejar al sistema sin quien administre. La misma barandilla que
             * en updateRole, porque este formulario llega a lo mismo.
             */
            abort_if(
                $user->is($request->user()) && $roles && ! $user->hasAllRoles($roles),
                422,
                'No puedes cambiar tu propio rol.',
            );

            if ($roles) {
                $data['roles'] = $roles;
            }
        }

        $this->users->update($user, $data);

        return redirect()
            ->route('users.show', $user)
            ->with('success', "Datos de {$user->name} actualizados.");
    }

    /** DELETE /usuarios/{user} */
    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        $name = $user->name;

        $this->users->delete($user);

        return redirect()
            ->route('users.index')
            ->with('success', "Cuenta de {$name} eliminada.");
    }

    /**
     * Ficha común a `show` y `edit`. Incluye qué puede hacer quien mira, para
     * que la vista no tenga que deducirlo de los roles.
     *
     * @return array<string, mixed>
     */
    private function present(Request $request, User $user): array
    {
        $user->loadMissing('roles', 'microsoftAccount');

        $account = $user->microsoftAccount;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->roles->pluck('name'),
            'permissions' => $user->getAllPermissions()->pluck('name')->sort()->values(),
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            'created_at' => $user->created_at?->toIso8601String(),
            'updated_at' => $user->updated_at?->toIso8601String(),
            'is_self' => $user->is($request->user()),
            'microsoft' => $account ? [
                'email' => $account->email,
                'display_name' => $account->display_name,
                'linked_at' => $account->created_at?->toIso8601String(),
                'synced_at' => $account->synced_at?->toIso8601String(),
                'can_read_calendar' => $account->canReadCalendar(),
            ] : null,
            'can' => [
                'update' => $request->user()->can('update', $user),
                'delete' => $request->user()->can('delete', $user),
            ],
        ];
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
