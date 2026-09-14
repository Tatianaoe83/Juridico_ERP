<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthService
{
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
