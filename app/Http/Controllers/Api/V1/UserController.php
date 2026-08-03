<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\V1\UserResource;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Cada método autoriza contra UserPolicy antes de tocar nada.
 *
 * Se hace con llamadas explícitas y no con `authorizeResource()`: desde
 * Laravel 11 el controlador base ya no tiene `middleware()`, del que ese
 * atajo depende, y falla en tiempo de ejecución.
 *
 * Y se hace con Policy, no con middleware `can:` en las rutas, porque la
 * Policy sí ve el registro concreto: un permiso dice «puede borrar usuarios»,
 * pero solo la Policy sabe que ESE usuario es un superadmin.
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

        $user = $this->users->create($request->validated());

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

        return new UserResource($this->users->update($user, $request->validated()));
    }

    /**
     * DELETE /api/v1/users/{user}
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        /*
         * Se comprueba aquí y no solo en la Policy porque el Gate::before del
         * superadmin devuelve true antes de que ninguna Policy se ejecute: se
         * salta los permisos, y con ellos también las barandillas.
         *
         * Borrarse a uno mismo no es un permiso que falte, es un estado en el
         * que la aplicación no debe poder quedar.
         */
        abort_if($user->is($request->user()), 422, 'No puedes eliminar tu propia cuenta.');

        $this->users->delete($user);

        return response()->json(status: 204);
    }
}
