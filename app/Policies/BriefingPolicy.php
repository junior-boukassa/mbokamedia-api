<?php

namespace App\Policies;

use App\Models\Briefing;
use App\Models\User;

class BriefingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view articles');
    }

    public function view(User $user, Briefing $briefing): bool
    {
        return $user->can('view articles');
    }

    public function create(User $user): bool
    {
        return $user->can('create articles');
    }

    public function update(User $user, Briefing $briefing): bool
    {
        return $user->can('edit articles');
    }

    public function delete(User $user, Briefing $briefing): bool
    {
        return $user->can('delete articles');
    }
}
