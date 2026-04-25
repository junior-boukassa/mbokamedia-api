<?php

namespace App\Services;

use App\Models\User;
use App\Support\AdminRoles;

class UserAccessManager
{
    public function captureSnapshot(User $user): array
    {
        return [
            'is_active' => (bool) $user->is_active,
            'roles' => AdminRoles::normalizeMany($user->getRoleNames())->sort()->values()->all(),
            'has_admin_access' => $user->hasAdminPanelAccess(),
        ];
    }

    public function synchronizeAccess(User $user, array $before): array
    {
        $after = $this->captureSnapshot($user);
        $rolesChanged = $before['roles'] !== $after['roles'];
        $deactivated = $before['is_active'] && ! $after['is_active'];
        $lostAdminAccess = $before['has_admin_access'] && ! $after['has_admin_access'];
        $shouldRevokeTokens = $rolesChanged || $deactivated || $lostAdminAccess;

        return [
            'before' => $before,
            'after' => $after,
            'roles_changed' => $rolesChanged,
            'deactivated' => $deactivated,
            'lost_admin_access' => $lostAdminAccess,
            'tokens_revoked' => $shouldRevokeTokens ? $user->tokens()->delete() : 0,
        ];
    }
}
