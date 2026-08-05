<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class AuthService
{
    /** Rol con el que nace toda cuenta nueva. */
    public const DEFAULT_ROLE = 'user';

    /**
     * Create a new user account.
     *
     * @param  array{name: string, email: string, password: string}  $data
     */
    public function register(array $data): User
    {
        $user = User::create($data);

        // Sin rol la cuenta entra pero choca con un 403 en la primera pantalla:
        // todas las rutas van detrás de `can:`.
        if (Role::where('name', self::DEFAULT_ROLE)->where('guard_name', 'web')->exists()) {
            $user->syncRoles(self::DEFAULT_ROLE);
        }

        return $user;
    }

    /**
     * Validate credentials without touching the session guard.
     *
     * @param  array{email: string, password: string}  $credentials
     *
     * @throws ValidationException
     */
    public function attemptStateless(array $credentials): User
    {
        /** @var User|null $user */
        $user = Auth::getProvider()->retrieveByCredentials($credentials);

        if (! $user || ! Auth::getProvider()->validateCredentials($user, $credentials)) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        return $user;
    }

    /**
     * Issue a Sanctum personal access token for the user.
     */
    public function issueToken(User $user, string $deviceName): string
    {
        return $user->createToken($deviceName)->plainTextToken;
    }

    /**
     * Revoke the token used on the current request.
     */
    public function revokeCurrentToken(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }
}
