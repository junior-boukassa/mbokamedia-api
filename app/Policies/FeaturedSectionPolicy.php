<?php

namespace App\Policies;

use App\Models\FeaturedSection;
use App\Models\User;

class FeaturedSectionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage featured sections');
    }

    public function view(User $user, FeaturedSection $featuredSection): bool
    {
        return $user->can('manage featured sections');
    }

    public function create(User $user): bool
    {
        return $user->can('manage featured sections');
    }

    public function update(User $user, FeaturedSection $featuredSection): bool
    {
        return $user->can('manage featured sections');
    }

    public function delete(User $user, FeaturedSection $featuredSection): bool
    {
        return $user->can('manage featured sections');
    }
}
