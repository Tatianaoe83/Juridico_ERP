<?php

namespace App\Policies;

use App\Models\User;

/**
 * Autorización sobre usuarios.
 *
 * Los permisos vienen de Spatie; aquí se añade lo que un permiso por sí solo no
 * puede expresar: que nadie se borre a sí mismo y que un admin no pueda tocar a
 * quien está por encima de él.
 */
class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('users.view');
    }

    public function view(User $actor, User $target): bool
    {
        return $actor->can('users.view') || $actor->is($target);
    }

    public function create(User $actor): bool
    {
        return $actor->can('users.create');
    }

    public function update(User $actor, User $target): bool
    {
        // Cualquiera edita su propio perfil aunque no administre usuarios.
        if ($actor->is($target)) {
            return true;
        }

        return $actor->can('users.update') && ! $this->outranks($target, $actor);
    }

    public function delete(User $actor, User $target): bool
    {
        // Borrarse a uno mismo deja la cuenta huérfana y, si era el único
        // superadmin, el sistema sin quien administre.
        if ($actor->is($target)) {
            return false;
        }

        return $actor->can('users.delete') && ! $this->outranks($target, $actor);
    }

    /** Un admin no puede modificar ni borrar a un superadmin. */
    private function outranks(User $target, User $actor): bool
    {
        return $target->hasRole('superadmin') && ! $actor->hasRole('superadmin');
    }
}
