<?php

namespace App\Policies;

use App\Models\Live;
use App\Models\User;

class LivePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view videos');
    }

    public function view(User $user, Live $live): bool
    {
        return $user->can('view videos');
    }

    public function create(User $user): bool
    {
        return $user->can('create videos');
    }

    public function update(User $user, Live $live): bool
    {
        return $user->can('edit videos');
    }

    public function delete(User $user, Live $live): bool
    {
        return $user->can('delete videos');
    }
}
