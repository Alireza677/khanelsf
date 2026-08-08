<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;

class ClientCustomerResolver
{
    /** @return Collection<int, Customer> */
    public function accessibleCustomers(User $user): Collection
    {
        if (! $user->isClient() || ! $user->isActive()) {
            return new Collection;
        }

        return $user->customers()
            ->where('customers.status', Customer::STATUS_ACTIVE)
            ->orderBy('customers.display_name')
            ->get();
    }

    /**
     * @throws AuthorizationException
     */
    public function resolve(User $user, ?int $requestedCustomerId = null): ?Customer
    {
        $customers = $this->accessibleCustomers($user);

        if ($requestedCustomerId === null) {
            return $customers->first();
        }

        $customer = $customers->firstWhere('id', $requestedCustomerId);

        if (! $customer) {
            throw new AuthorizationException('This customer is not accessible.');
        }

        return $customer;
    }
}
