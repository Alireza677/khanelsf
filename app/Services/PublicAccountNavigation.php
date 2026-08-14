<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Contracts\Auth\Factory as AuthFactory;

class PublicAccountNavigation
{
    public function __construct(private readonly AuthFactory $auth) {}

    /** @return array<string, mixed> */
    public function present(): array
    {
        $user = $this->auth->guard('client')->user();
        $user = $user instanceof User && ! $user->is_admin && $user->status === 'active'
            ? $user
            : null;
        $hasCustomerCapability = $user?->customers()
            ->where('customers.status', Customer::STATUS_ACTIVE)
            ->exists() ?? false;

        return [
            'authenticated' => $user !== null,
            'user' => $user,
            'name' => $user?->name,
            'mobile' => $user?->mobile,
            'status' => $user?->status,
            'status_label' => match ($user?->status) {
                'active' => 'فعال',
                default => null,
            },
            'has_customer_capability' => $hasCustomerCapability,
            'login_url' => route('login'),
            'register_url' => route('register'),
            'account_url' => route('account.home'),
            'profile_url' => route('account.profile.edit'),
            'orders_url' => route('account.orders.index'),
            'services_url' => $hasCustomerCapability ? route('account.services.index') : null,
            'projects_url' => $hasCustomerCapability ? route('account.projects.index') : null,
            'logout_url' => route('client.logout'),
        ];
    }
}
