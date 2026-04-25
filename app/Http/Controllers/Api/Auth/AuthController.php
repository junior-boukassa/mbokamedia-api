<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AdminAuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends ApiController
{
    public function __construct(
        protected AdminAuditLogger $adminAuditLogger,
    ) {
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $deviceName = (string) $request->input('device_name', 'admin-panel');
        $user = User::query()
            ->with('roles')
            ->where('email', $request->string('email'))
            ->first();

        if (! $user || ! Hash::check($request->string('password'), $user->password) || ! $user->is_active) {
            $this->adminAuditLogger->log(
                'auth.login_denied',
                actor: $user,
                request: $request,
                status: 'denied',
                target: $user ?? 'auth',
                targetType: 'auth',
                targetLabel: $request->string('email')->toString(),
                metadata: [
                    'reason' => ! $user ? 'user_not_found' : (! $user->is_active ? 'inactive_account' : 'invalid_password'),
                ],
                actorEmail: $user?->email ?? $request->string('email')->toString(),
            );

            return $this->error('Invalid credentials.', 401);
        }

        if (! $user->hasAdminPanelAccess()) {
            $this->adminAuditLogger->log(
                'auth.login_denied',
                actor: $user,
                request: $request,
                status: 'denied',
                target: $user,
                targetType: 'auth',
                metadata: [
                    'reason' => 'admin_access_denied',
                ],
            );

            return $this->error('This account is not authorized to access the admin panel.', 403);
        }

        $requestKey = $this->resolveRateLimitKey($request);
        $expiresAt = config('sanctum.expiration') !== null
            ? now()->addMinutes((int) config('sanctum.expiration'))
            : null;

        $user->tokens()
            ->where('name', $deviceName)
            ->orWhere(function ($query): void {
                $query->whereNotNull('expires_at')
                    ->where('expires_at', '<=', now());
            })
            ->delete();

        $user->forceFill(['last_login_at' => now()])->save();

        $token = $user->createToken($deviceName, ['*'], $expiresAt)->plainTextToken;
        RateLimiter::clear($requestKey);

        $this->adminAuditLogger->log(
            'auth.login_succeeded',
            actor: $user,
            request: $request,
            target: $user,
            targetType: 'auth',
            metadata: [
                'device_name' => $deviceName,
            ],
        );

        return $this->success([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => UserResource::make($user->load('roles')),
        ], 'Login successful.');
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! Hash::check($request->string('current_password'), $user->password)) {
            return $this->error('Current password is incorrect.', 422, [
                'current_password' => ['The provided current password is incorrect.'],
            ]);
        }

        $user->update([
            'password' => $request->string('password'),
        ]);

        $user->tokens()->delete();

        return $this->success(null, 'Password updated successfully.');
    }

    public function logout(Request $request): JsonResponse
    {
        $this->adminAuditLogger->log(
            'auth.logout',
            actor: $request->user(),
            request: $request,
            target: $request->user(),
            targetType: 'auth',
        );

        $request->user()?->currentAccessToken()?->delete();

        return $this->success(null, 'Logout successful.');
    }

    protected function resolveRateLimitKey(Request $request): string
    {
        return sprintf(
            '%s|%s',
            Str::lower((string) $request->input('email')),
            $request->ip(),
        );
    }
}
