<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Requests\Api\V1\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends ApiController
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::query()->create([
            'name' => $request->string('name')->toString(),
            'email' => $request->string('email')->toString(),
            'password' => $request->string('password')->toString(),
            'is_active' => true,
        ]);

        $token = Auth::guard('api')->login($user);

        return $this->success(
            $this->tokenPayload($token, $user),
            'Registration successful.',
            201,
        );
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::query()
            ->where('email', $request->string('email')->toString())
            ->first();

        if (! $user || ! Hash::check($request->string('password')->toString(), $user->password)) {
            return $this->error('Invalid credentials.', 401);
        }

        if (! $user->is_active) {
            return $this->error('This account is inactive.', 403);
        }

        $token = Auth::guard('api')->attempt($request->only(['email', 'password']));

        if (! $token) {
            return $this->error('Unable to create token.', 401);
        }

        $user->forceFill(['last_login_at' => now()])->save();

        return $this->success(
            $this->tokenPayload($token, $user->fresh()),
            'Login successful.',
        );
    }

    public function me(Request $request): JsonResponse
    {
        return $this->success(
            UserResource::make($request->user()->loadMissing('roles')),
            'Authenticated user retrieved.',
        );
    }

    public function logout(): JsonResponse
    {
        Auth::guard('api')->logout();

        return $this->success(null, 'Logout successful.');
    }

    public function refresh(Request $request): JsonResponse
    {
        $token = $request->bearerToken();

        if (! $token) {
            return $this->error('A bearer token is required to refresh the session.', 401);
        }

        $refreshedToken = Auth::guard('api')->setToken($token)->refresh();
        $user = Auth::guard('api')->setToken($refreshedToken)->user();

        return $this->success(
            $this->tokenPayload($refreshedToken, $user),
            'Token refreshed successfully.',
        );
    }

    protected function tokenPayload(string $token, User $user): array
    {
        return [
            'access_token' => $token,
            'refresh_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => Auth::guard('api')->factory()->getTTL() * 60,
            'refresh_expires_in' => (int) config('jwt.refresh_ttl', 20160) * 60,
            'user' => UserResource::make($user->loadMissing('roles')),
        ];
    }
}
