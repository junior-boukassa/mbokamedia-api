<?php

namespace App\Policies;

use App\Models\BreakingNews;
use App\Models\User;

class BreakingNewsPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view breaking news');
    }

    public function view(User $user, BreakingNews $breakingNews): bool
    {
        return $user->can('view breaking news');
    }

    public function create(User $user): bool
    {
        return $user->can('manage breaking news');
    }

    public function update(User $user, BreakingNews $breakingNews): bool
    {
        return $user->can('manage breaking news');
    }

    public function delete(User $user, BreakingNews $breakingNews): bool
    {
        return $user->can('manage breaking news');
    }
}
