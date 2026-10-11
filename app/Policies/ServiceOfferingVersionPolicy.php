<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ServiceOfferingVersionPolicy extends ServiceOfferingPolicy
{
    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Model $record): bool
    {
        return parent::update($user, $record) && ! $record->isPublished();
    }
}
