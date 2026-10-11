<?php

namespace App\Policies;

use App\Models\ClientProjectWorkItem;
use App\Models\User;
use App\Services\ClientDeliveryAccess;
use Illuminate\Support\Facades\Gate;

class ClientProjectWorkItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() && $user->isActive();
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function view(User $user, ClientProjectWorkItem $item): bool
    {
        return ($this->viewAny($user) && Gate::forUser($user)->allows('update', $item->project))
            || app(ClientDeliveryAccess::class)->allows($user, $item);
    }

    public function update(User $user, ClientProjectWorkItem $item): bool
    {
        return $this->viewAny($user) && Gate::forUser($user)->allows('update', $item->project);
    }

    public function review(User $user, ClientProjectWorkItem $item): bool
    {
        return app(ClientDeliveryAccess::class)->allows($user, $item, true);
    }

    public function delete(User $user, ClientProjectWorkItem $item): bool
    {
        return false;
    }
}
