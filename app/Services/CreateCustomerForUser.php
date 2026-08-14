<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateCustomerForUser
{
    public function __construct(private readonly CustomerMembershipManager $memberships) {}

    public function handle(User $user, array $attributes): Customer
    {
        return DB::transaction(function () use ($user, $attributes): Customer {
            $customer = Customer::query()->create($attributes);
            $this->memberships->attach($customer, $user, 'owner', true);

            return $customer;
        });
    }
}
