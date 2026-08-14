<?php

namespace App\Services;

class ClientPortalNavigation
{
    private array $items = [];

    public function __construct(private readonly PublicAccountNavigation $accounts)
    {
        $this->items = [
            ['key' => 'account', 'label' => 'حساب کاربری', 'route' => 'account.home', 'icon' => '⌂'],
            ['key' => 'profile', 'label' => 'پروفایل من', 'route' => 'account.profile.edit', 'icon' => '○'],
            ['key' => 'orders', 'label' => 'سفارش‌های من', 'route' => 'account.orders.index', 'active_routes' => 'account.orders.*', 'icon' => '▤'],
            ['key' => 'services', 'label' => 'خدمات و پروژه‌های من', 'route' => 'account.services.index', 'active_routes' => ['account.services.*', 'client.dashboard'], 'icon' => '◫', 'requires_customer' => true],
            ['key' => 'projects', 'label' => 'پروژه‌ها', 'route' => 'account.projects.index', 'active_routes' => ['account.projects.*', 'client.projects.*'], 'icon' => '▦', 'requires_customer' => true],
        ];
    }

    public function register(array $item): void
    {
        $this->items[] = $item;
    }

    public function items(): array
    {
        $hasCustomerCapability = $this->accounts->present()['has_customer_capability'];

        return array_values(array_filter(
            $this->items,
            fn (array $item): bool => ! ($item['requires_customer'] ?? false) || $hasCustomerCapability,
        ));
    }
}
