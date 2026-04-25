<?php

namespace App\Policies;

use App\Models\Medium;
use App\Models\User;

class MediumPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage media');
    }

    public function view(User $user, Medium $medium): bool
    {
        return $user->can('manage media');
    }

    public function create(User $user): bool
    {
        return $user->can('manage media');
    }

    public function delete(User $user, Medium $medium): bool
    {
        return $user->can('manage media');
    }
}
