<?php

namespace App\Services;

class ClientPortalNavigation
{
    private array $items = [];

    public function __construct()
    {
        $this->items = [
            ['key' => 'dashboard', 'label' => 'داشبورد', 'route' => 'client.dashboard', 'icon' => '⌂'],
            ['key' => 'projects', 'label' => 'پروژه‌ها', 'route' => 'client.projects.index', 'active_routes' => 'client.projects.*', 'icon' => '▦'],
            ['key' => 'reports', 'label' => 'گزارش‌ها', 'route' => 'client.placeholder.reports', 'icon' => '▤', 'coming_soon' => true],
            ['key' => 'invoices', 'label' => 'فاکتورها', 'route' => 'client.placeholder.invoices', 'icon' => '◫', 'coming_soon' => true],
            ['key' => 'files', 'label' => 'فایل‌ها', 'route' => 'client.placeholder.files', 'icon' => '▱', 'coming_soon' => true],
            ['key' => 'profile', 'label' => 'پروفایل', 'route' => 'client.profile.edit', 'icon' => '○'],
        ];
    }

    public function register(array $item): void
    {
        $this->items[] = $item;
    }

    public function items(): array
    {
        return $this->items;
    }
}
