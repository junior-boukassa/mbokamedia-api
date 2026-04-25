<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage users');
    }

    public function view(User $user, User $model): bool
    {
        if (! $user->can('manage users')) {
            return false;
        }

        return ! $model->isSuperAdmin() || $user->isSuperAdmin();
    }

    public function create(User $user): bool
    {
        return $user->can('manage users');
    }

    public function update(User $user, User $model): bool
    {
        if (! $user->can('manage users')) {
            return false;
        }

        return ! $model->isSuperAdmin() || $user->isSuperAdmin();
    }

    public function delete(User $user, User $model): bool
    {
        if (! $user->can('delete users') || $user->id === $model->id) {
            return false;
        }

        return ! $model->isSuperAdmin() || $user->isSuperAdmin();
    }
}
