<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() && $user->isActive();
    }

    public function view(User $user, Customer $customer): bool
    {
        if ($user->isAdmin() && $user->isActive()) {
            return true;
        }

        return $user->isClient()
            && $user->isActive()
            && $customer->isActive()
            && $customer->users()->whereKey($user->getKey())->exists();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() && $user->isActive();
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->isAdmin() && $user->isActive();
    }

    public function delete(User $user, Customer $customer): bool
    {
        return false;
    }

    public function restore(User $user, Customer $customer): bool
    {
        return false;
    }

    public function forceDelete(User $user, Customer $customer): bool
    {
        return false;
    }
}
