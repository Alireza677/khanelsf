<?php

namespace App\Policies;

use App\Models\ClientProjectCycle;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class ClientProjectCyclePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() && $user->isActive();
    }

    public function view(User $user, ClientProjectCycle $cycle): bool
    {
        return $this->viewAny($user) && Gate::forUser($user)->allows('update', $cycle->project);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ClientProjectCycle $cycle): bool
    {
        return false;
    }

    public function delete(User $user, ClientProjectCycle $cycle): bool
    {
        return false;
    }
}
