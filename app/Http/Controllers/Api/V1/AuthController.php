<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Resources\V1\UserResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $auth) {}

    /**
     * POST /api/v1/auth/login
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = $this->auth->attemptStateless($request->only('email', 'password'));

        return response()->json([
            'data' => new UserResource($user->load('roles')),
            'token' => $this->auth->issueToken($user, $request->deviceName()),
            'token_type' => 'Bearer',
        ]);
    }

    /**
     * GET /api/v1/auth/me
     */
    public function me(Request $request): UserResource
    {
        return new UserResource($request->user()->load('roles'));
    }

    /**
     * POST /api/v1/auth/logout — revokes only the current token.
     */
    public function logout(Request $request): JsonResponse
    {
        $this->auth->revokeCurrentToken($request->user());

        return response()->json(['message' => 'Sesión cerrada.']);
    }
}
