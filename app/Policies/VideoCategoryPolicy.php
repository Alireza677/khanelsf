<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VideoCategory;

class VideoCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() && $user->isActive();
    }

    public function view(User $user, VideoCategory $category): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, VideoCategory $category): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, VideoCategory $category): bool
    {
        return $this->viewAny($user);
    }

    public function deleteAny(User $user): bool
    {
        return $this->viewAny($user);
    }
}
