<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Notifications\AdminOtpNotification;
use App\Notifications\PasswordChangeRequiredNotification;
use App\Services\AdminAuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Throwable;

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

        if (! $this->issueOtpChallenge($user)) {
            return $this->error('Impossible d’envoyer le code OTP pour le moment. Merci de reessayer dans un instant.', 503);
        }

        $this->adminAuditLogger->log(
            'auth.otp_challenge_sent',
            actor: $user,
            request: $request,
            target: $user,
            targetType: 'auth',
            metadata: [
                'device_name' => $deviceName,
            ],
        );

        return $this->success([
            'otp_required' => true,
            'email' => $user->email,
            'masked_email' => $this->maskEmail($user->email),
            'expires_in_minutes' => 10,
        ], 'OTP challenge sent successfully.');
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->must_change_password && ! Hash::check($request->string('current_password'), $user->password)) {
            return $this->error('Current password is incorrect.', 422, [
                'current_password' => ['The provided current password is incorrect.'],
            ]);
        }

        $user->update([
            'password' => $request->string('password'),
            'must_change_password' => false,
            'temporary_password_set_at' => null,
        ]);

        $currentTokenId = $user->currentAccessToken()?->id;
        $user->tokens()
            ->when($currentTokenId !== null, fn ($query) => $query->where('id', '!=', $currentTokenId))
            ->delete();

        return $this->success(null, 'Password updated successfully.');
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'digits:6'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $deviceName = (string) ($validated['device_name'] ?? 'admin-panel');
        $user = User::query()
            ->with('roles')
            ->where('email', $validated['email'])
            ->first();

        if (! $user || ! $user->hasAdminPanelAccess()) {
            return $this->error('OTP verification cannot be completed for this account.', 404);
        }

        if (! $user->otp_code_hash || ! $user->otp_expires_at) {
            return $this->error('No active OTP challenge found. Please restart the login process.', 422);
        }

        if ($user->otp_attempts >= 5) {
            $user->clearOtpChallenge();

            $this->adminAuditLogger->log(
                'auth.otp_blocked',
                actor: $user,
                request: $request,
                status: 'denied',
                target: $user,
                targetType: 'auth',
            );

            return $this->error('Too many invalid OTP attempts. Please request a new code.', 422);
        }

        if ($user->otp_expires_at->isPast()) {
            $user->clearOtpChallenge();

            $this->adminAuditLogger->log(
                'auth.otp_expired',
                actor: $user,
                request: $request,
                status: 'denied',
                target: $user,
                targetType: 'auth',
            );

            return $this->error('Code OTP invalide ou expiré. Veuillez vérifier le code reçu par email.', 422);
        }

        if (! Hash::check((string) $validated['code'], $user->otp_code_hash)) {
            $user->increment('otp_attempts');

            $this->adminAuditLogger->log(
                'auth.otp_failed',
                actor: $user,
                request: $request,
                status: 'denied',
                target: $user,
                targetType: 'auth',
                metadata: [
                    'attempts' => $user->fresh()->otp_attempts,
                ],
            );

            return $this->error('Code OTP invalide ou expiré. Veuillez vérifier le code reçu par email.', 422);
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

        $user->forceFill([
            'last_login_at' => now(),
        ])->save();
        $user->clearOtpChallenge();

        $token = $user->createToken($deviceName, ['*'], $expiresAt)->plainTextToken;
        RateLimiter::clear($requestKey);

        if ($user->must_change_password) {
            $user->notify(new PasswordChangeRequiredNotification($this->resolveAdminUrl('/security/change-password')));
        }

        $this->adminAuditLogger->log(
            'auth.login_succeeded',
            actor: $user,
            request: $request,
            target: $user,
            targetType: 'auth',
            metadata: [
                'device_name' => $deviceName,
                'otp_verified' => true,
            ],
        );

        return $this->success([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => UserResource::make($user->fresh()->load('roles')),
        ], 'OTP verified successfully.');
    }

    public function resendOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::query()
            ->with('roles')
            ->where('email', $validated['email'])
            ->first();

        if (! $user || ! $user->hasAdminPanelAccess() || ! $user->is_active) {
            return $this->error('OTP challenge cannot be resent for this account.', 404);
        }

        if (! $this->issueOtpChallenge($user)) {
            return $this->error('Impossible d’envoyer un nouveau code OTP pour le moment. Merci de reessayer dans un instant.', 503);
        }

        $this->adminAuditLogger->log(
            'auth.otp_resent',
            actor: $user,
            request: $request,
            target: $user,
            targetType: 'auth',
        );

        return $this->success([
            'otp_required' => true,
            'email' => $user->email,
            'masked_email' => $this->maskEmail($user->email),
            'expires_in_minutes' => 10,
        ], 'OTP challenge resent successfully.');
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

    protected function issueOtpChallenge(User $user): bool
    {
        $code = (string) random_int(100000, 999999);

        $user->forceFill([
            'otp_code_hash' => Hash::make($code),
            'otp_expires_at' => now()->addMinutes(10),
            'otp_attempts' => 0,
        ])->save();

        try {
            $user->notify(new AdminOtpNotification($code));
        } catch (Throwable $exception) {
            Log::error('otp.email_send_failed', [
                'user_id' => $user->id,
                'email' => $user->email,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }

        Log::info('otp.email_sent', [
            'user_id' => $user->id,
            'email' => $user->email,
            'mailer' => config('mail.default'),
            'expires_at' => $user->otp_expires_at?->toIso8601String(),
        ]);

        return true;
    }

    protected function maskEmail(string $email): string
    {
        [$local, $domain] = explode('@', $email, 2);
        $visible = Str::substr($local, 0, min(2, Str::length($local)));

        return $visible.str_repeat('*', max(1, Str::length($local) - Str::length($visible))).'@'.$domain;
    }

    protected function resolveAdminUrl(string $path): string
    {
        $baseUrl = rtrim((string) env('ADMIN_PANEL_URL', env('APP_URL', 'http://localhost')), '/');

        return $baseUrl.$path;
    }
}
