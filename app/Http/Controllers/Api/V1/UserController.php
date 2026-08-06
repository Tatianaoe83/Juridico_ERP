<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\V1\UserResource;
use App\Models\User;
use App\Services\AuthService;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Cada método autoriza contra UserPolicy antes de tocar nada.

 */
class UserController extends Controller
{
    public function __construct(private readonly UserService $users) {}

    /**
     * GET /api/v1/users
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', User::class);

        return UserResource::collection(
            $this->users->paginate($request->only('search', 'per_page'))
        );
    }

    /**
     * POST /api/v1/users
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $data = $request->validated();

        /*
         * `users.create` solo autoriza a dar de alta, no a repartir roles: sin
         * este filtro cualquiera con ese permiso podría nombrarse superadmin
         * de paso. Mismo criterio que UserController::store en la parte Web.
         */
        $roles = $request->user()->can('roles.manage')
            ? array_filter($data['roles'] ?? [])
            : [];

        $data['roles'] = $roles ?: [AuthService::DEFAULT_ROLE];

        $user = $this->users->create($data);

        return (new UserResource($user))->response()->setStatusCode(201);
    }

    /**
     * GET /api/v1/users/{user}
     */
    public function show(User $user): UserResource
    {
        $this->authorize('view', $user);

        return new UserResource($user->load('roles'));
    }

    /**
     * PUT|PATCH /api/v1/users/{user}
     */
    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $this->authorize('update', $user);

        $data = $request->validated();

        /*
         * `update` autoriza autoedición sin más (cualquiera edita su propio
         * nombre o correo), así que el filtro de roles va aparte: sin esto,
         * mandar `roles: ["superadmin"]` contra el propio id bastaba para
         * autopromoverse. Mismo criterio que UserController::update Web.
         */
        $roles = ($request->user()->can('roles.manage') && array_key_exists('roles', $data))
            ? array_filter($data['roles'])
            : null;

        abort_if(
            $roles && $user->is($request->user()) && ! $user->hasAllRoles($roles),
            422,
            'No puedes cambiar tu propio rol.',
        );

        unset($data['roles']);

        if ($roles) {
            $data['roles'] = $roles;
        }

        return new UserResource($this->users->update($user, $data));
    }

    /**
     * DELETE /api/v1/users/{user}
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        abort_if($user->is($request->user()), 422, 'No puedes eliminar tu propia cuenta.');

        $this->users->delete($user);

        return response()->json(status: 204);
    }
}
