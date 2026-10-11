<?php

namespace App\Services;

use App\Models\ClientProject;
use App\Models\ClientProjectWorkItem;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class ClientDeliveryAccess
{
    public function allows(User $actor, ClientProjectWorkItem $item, bool $owner = false, bool $lock = false): bool
    {
        // Re-query on every request/mutation; loaded membership relations are not authority.
        // Review authorization uses locking reads, not an old MySQL REPEATABLE READ snapshot.
        $project = ClientProject::whereKey($item->client_project_id)->when($lock, fn ($query) => $query->lockForUpdate())->first();
        $user = User::whereKey($actor->id)->when($lock, fn ($query) => $query->lockForUpdate())->first();
        if (! $project || ! $user || ! $user->isActive() || ! $user->isClient() || $item->visibility !== 'client') {
            return false;
        }
        $customer = Customer::whereKey($project->customer_id)->when($lock, fn ($query) => $query->lockForUpdate())->first();
        $membership = DB::table('customer_user')->where('customer_id', $project->customer_id)->where('user_id', $user->id)
            ->when($lock, fn ($query) => $query->lockForUpdate())->first();

        return $customer?->isActive() && $membership && (! $owner || $membership->membership_role === 'owner');
    }

    public function authorize(User $actor, ClientProjectWorkItem $item, bool $owner = false, bool $lock = false): void
    {
        if (! $this->allows($actor, $item, $owner, $lock)) {
            throw new AuthorizationException;
        }
    }
}
