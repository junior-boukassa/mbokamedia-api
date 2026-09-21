<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Notifications\NewAdminWelcomeNotification;
use App\Services\AdminAuditLogger;
use App\Services\UserAccessManager;
use App\Support\AdminRoles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class UserController extends ApiController
{
    public function __construct(
        protected UserAccessManager $userAccessManager,
        protected AdminAuditLogger $adminAuditLogger,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()
            ->with('roles')
            ->when(! $request->user()->isSuperAdmin(), function ($query): void {
                $query->whereDoesntHave('roles', fn ($rolesQuery) => $rolesQuery->whereIn('name', AdminRoles::matchingNames('super_admin')));
            })
            ->when($request->filled('search'), function ($query) use ($request): void {
                $query->where(function ($subQuery) use ($request): void {
                    $subQuery->where('name', 'like', '%'.$request->string('search').'%')
                        ->orWhere('email', 'like', '%'.$request->string('search').'%');
                });
            })
            ->when($request->filled('role'), function ($query) use ($request): void {
                $normalizedRole = AdminRoles::normalize($request->string('role')->toString());

                $query->whereHas('roles', fn ($rolesQuery) => $rolesQuery->whereIn('name', AdminRoles::matchingNames($normalizedRole)));
            })
            ->when($request->has('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->latest()
            ->paginate((int) $request->integer('per_page', config('api.pagination.per_page')));

        return $this->paginated(UserResource::collection($users), 'Users retrieved successfully.');
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $roles = $request->input('roles', []);
        $this->ensureAssignableRoles($request->user(), $roles);

        $safe = $request->safe()->except(['roles', 'avatar']);
        $temporaryPassword = (string) ($safe['password'] ?? '');

        if ($request->hasFile('avatar')) {
            $safe['avatar_path'] = $request->file('avatar')->store('avatars', 'public');
        }

        $user = User::query()->create([
            ...$safe,
            'must_change_password' => true,
            'temporary_password_set_at' => now(),
        ]);
        $user->syncRoles($roles);
        $user->notify(
            new NewAdminWelcomeNotification(
                rtrim((string) env('ADMIN_PANEL_URL', env('APP_URL', 'http://localhost')), '/').'/login',
                $user->email,
                $temporaryPassword,
            ),
        );

        $this->adminAuditLogger->log(
            'user.created',
            actor: $request->user(),
            request: $request,
            target: $user->load('roles'),
            metadata: [
                'roles' => AdminRoles::normalizeMany($roles)->all(),
                'is_active' => $user->is_active,
                'must_change_password' => $user->must_change_password,
            ],
        );

        return $this->success(UserResource::make($user->load('roles')), 'User created successfully.', 201);
    }

    public function show(User $user): JsonResponse
    {
        $this->authorize('view', $user);

        return $this->success(UserResource::make($user->load('roles')), 'User retrieved successfully.');
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $before = $this->userAccessManager->captureSnapshot($user);
        $data = $request->validated();
        $previousAvatarPath = $user->avatar_path;
        $newAvatarPath = null;

        unset($data['avatar'], $data['remove_avatar']);

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        if ($request->has('roles')) {
            $this->ensureAssignableRoles($request->user(), $request->input('roles', []), $user);
        }

        if ($request->user()->id === $user->id && array_key_exists('is_active', $data) && ! $request->boolean('is_active')) {
            return $this->error('You cannot deactivate your own account.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($request->hasFile('avatar')) {
            $newAvatarPath = $request->file('avatar')->store('avatars', 'public');
            $data['avatar_path'] = $newAvatarPath;
        } elseif ($request->boolean('remove_avatar')) {
            $data['avatar_path'] = null;
        }

        try {
            $user->update(collect($data)->except('roles')->all());
        } catch (\Throwable $exception) {
            if ($newAvatarPath !== null) {
                Storage::disk('public')->delete($newAvatarPath);
            }

            throw $exception;
        }

        if (array_key_exists('avatar_path', $data) && $previousAvatarPath !== $data['avatar_path']) {
            $this->deleteManagedAvatar($previousAvatarPath);
        }

        if ($request->has('roles')) {
            $user->syncRoles($request->input('roles', []));
        }

        $user->refresh()->load('roles');
        $accessUpdate = $this->userAccessManager->synchronizeAccess($user, $before);

        $this->adminAuditLogger->log(
            'user.updated',
            actor: $request->user(),
            request: $request,
            target: $user,
            metadata: [
                'roles_before' => $before['roles'],
                'roles_after' => $accessUpdate['after']['roles'],
                'was_active' => $before['is_active'],
                'is_active' => $accessUpdate['after']['is_active'],
                'tokens_revoked' => $accessUpdate['tokens_revoked'],
            ],
        );

        if ($accessUpdate['roles_changed']) {
            $this->adminAuditLogger->log(
                'user.roles_changed',
                actor: $request->user(),
                request: $request,
                target: $user,
                metadata: [
                    'roles_before' => $before['roles'],
                    'roles_after' => $accessUpdate['after']['roles'],
                    'tokens_revoked' => $accessUpdate['tokens_revoked'],
                ],
            );
        }

        if ($before['is_active'] !== $accessUpdate['after']['is_active']) {
            $this->adminAuditLogger->log(
                $accessUpdate['after']['is_active'] ? 'user.reactivated' : 'user.deactivated',
                actor: $request->user(),
                request: $request,
                target: $user,
                metadata: [
                    'tokens_revoked' => $accessUpdate['tokens_revoked'],
                ],
            );
        }

        return $this->success(UserResource::make($user), 'User updated successfully.');
    }

    public function destroy(User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        $user->tokens()->delete();
        $this->adminAuditLogger->log(
            'user.deleted',
            actor: request()->user(),
            request: request(),
            target: $user,
        );
        $user->delete();

        return $this->success(null, 'User deleted successfully.');
    }

    protected function ensureAssignableRoles(User $actor, array $roles, ?User $subject = null): void
    {
        abort_unless($actor->can('assign roles'), Response::HTTP_FORBIDDEN);

        $normalizedRoles = AdminRoles::normalizeMany($roles);

        if ($normalizedRoles->contains('super_admin') && ! $actor->isSuperAdmin()) {
            abort(Response::HTTP_FORBIDDEN, 'Only a super admin can assign the super_admin role.');
        }

        if ($subject?->isSuperAdmin() && ! $actor->isSuperAdmin()) {
            abort(Response::HTTP_FORBIDDEN, 'Only a super admin can manage another super admin account.');
        }
    }

    protected function deleteManagedAvatar(?string $path): void
    {
        if (blank($path) || Str::startsWith($path, ['http://', 'https://'])) {
            return;
        }

        $normalizedPath = Str::after($path, '/storage/');

        if (! Str::startsWith($normalizedPath, 'avatars/')) {
            return;
        }

        Storage::disk('public')->delete($normalizedPath);
    }
}
