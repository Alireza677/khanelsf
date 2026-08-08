<?php

namespace App\Policies;

use App\Models\ClientProjectActivity;
use App\Models\User;

class ClientProjectActivityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() && $user->isActive();
    }

    public function view(User $user, ClientProjectActivity $activity): bool
    {
        return $user->isAdmin() && $user->isActive();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() && $user->isActive();
    }

    public function update(User $user, ClientProjectActivity $activity): bool
    {
        return $user->isAdmin() && $user->isActive();
    }

    public function delete(User $user, ClientProjectActivity $activity): bool
    {
        return false;
    }
}
