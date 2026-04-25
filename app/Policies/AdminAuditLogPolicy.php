<?php

namespace App\Policies;

use App\Models\AdminAuditLog;
use App\Models\User;

class AdminAuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view audit logs');
    }

    public function view(User $user, AdminAuditLog $adminAuditLog): bool
    {
        return $user->can('view audit logs');
    }
}
