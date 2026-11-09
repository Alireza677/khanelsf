<?php

namespace App\Policies;

use App\Models\ProductAttribute;
use App\Models\ProductAttributeValue;
use App\Models\User;
use App\Services\ModuleService;
use Illuminate\Database\Eloquent\Model;

class ProductCatalogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() && $user->isActive() && app(ModuleService::class)->shopEnabled();
    }

    public function view(User $user, Model $record): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Model $record): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, Model $record): bool
    {
        return $this->viewAny($user) && (! ($record instanceof ProductAttribute || $record instanceof ProductAttributeValue)
            || ! $record->assignments()->exists());
    }

    public function deleteAny(User $user): bool
    {
        // Delete records individually so in-use guards cannot be bypassed by a bulk query.
        return false;
    }
}
