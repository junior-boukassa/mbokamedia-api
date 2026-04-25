<?php

namespace App\Policies;

use App\Models\Article;
use App\Models\User;

class ArticlePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view articles');
    }

    public function view(User $user, Article $article): bool
    {
        return $user->can('view articles');
    }

    public function create(User $user): bool
    {
        return $user->can('create articles');
    }

    public function update(User $user, Article $article): bool
    {
        return $user->can('edit articles') || ($user->id === $article->author_id && $user->can('edit own articles'));
    }

    public function delete(User $user, Article $article): bool
    {
        return $user->can('delete articles');
    }
}
