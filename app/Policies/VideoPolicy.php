<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Video;

class VideoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view videos');
    }

    public function view(User $user, Video $video): bool
    {
        return $user->can('view videos');
    }

    public function create(User $user): bool
    {
        return $user->can('create videos');
    }

    public function update(User $user, Video $video): bool
    {
        return $user->can('edit videos');
    }

    public function delete(User $user, Video $video): bool
    {
        return $user->can('delete videos');
    }
}
