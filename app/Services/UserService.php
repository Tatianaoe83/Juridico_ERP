<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;

class UserService
{
    /** Columnas por las que se permite ordenar. */
    private const SORTABLE = ['id', 'name', 'email', 'created_at'];

    /**
     * Listado paginado con búsqueda y orden.
     *
     * @param  array{search?: string|null, sort?: string|null, direction?: string|null, per_page?: int|null}  $filters
     * @return LengthAwarePaginator<int, User>
     */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $search = Arr::get($filters, 'search');
        $sort = in_array(Arr::get($filters, 'sort'), self::SORTABLE, true)
            ? Arr::get($filters, 'sort')
            : 'id';
        $direction = Arr::get($filters, 'direction') === 'asc' ? 'asc' : 'desc';

        return User::query()
            ->with('roles')
            ->when($search, fn ($query) => $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            }))
            ->orderBy($sort, $direction)
            ->paginate(min((int) Arr::get($filters, 'per_page', 10), 100))
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): User
    {
        $user = User::create(Arr::only($data, ['name', 'email', 'password']));

        if (isset($data['roles'])) {
            $user->syncRoles($data['roles']);
        }

        return $user->load('roles');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, array $data): User
    {
        $user->fill(Arr::only($data, ['name', 'email', 'password']))->save();

        if (isset($data['roles'])) {
            $user->syncRoles($data['roles']);
        }

        return $user->load('roles');
    }

    public function delete(User $user): void
    {
        $user->tokens()->delete();
        $user->delete();
    }
}
