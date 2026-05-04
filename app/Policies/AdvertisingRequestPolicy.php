<?php

namespace App\Policies;

use App\Models\AdvertisingRequest;
use App\Models\User;

class AdvertisingRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage advertising requests');
    }

    public function view(User $user, AdvertisingRequest $advertisingRequest): bool
    {
        return $user->can('manage advertising requests');
    }

    public function update(User $user, AdvertisingRequest $advertisingRequest): bool
    {
        return $user->can('manage advertising requests');
    }
}
