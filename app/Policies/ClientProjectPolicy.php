<?php

namespace App\Policies;

use App\Models\ClientProject;
use App\Models\User;

class ClientProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() && $user->isActive();
    }

    public function view(User $user, ClientProject $project): bool
    {
        if ($user->isAdmin() && $user->isActive()) {
            return true;
        }

        return $user->isClient()
            && $user->isActive()
            && $project->customer()->where('status', 'active')->exists()
            && $project->customer->users()->whereKey($user->getKey())->exists();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() && $user->isActive();
    }

    public function update(User $user, ClientProject $project): bool
    {
        return $user->isAdmin() && $user->isActive();
    }

    public function delete(User $user, ClientProject $project): bool
    {
        return false;
    }
}
