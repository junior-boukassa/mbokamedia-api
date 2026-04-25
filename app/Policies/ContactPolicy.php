<?php

namespace App\Policies;

use App\Models\Contact;
use App\Models\User;

class ContactPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage contacts');
    }

    public function view(User $user, Contact $contact): bool
    {
        return $user->can('manage contacts');
    }

    public function update(User $user, Contact $contact): bool
    {
        return $user->can('manage contacts');
    }
}
