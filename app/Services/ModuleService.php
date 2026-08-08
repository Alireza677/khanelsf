<?php

namespace App\Services;

class ModuleService
{
    public function __construct(private readonly SettingsService $settings) {}

    public function projectsEnabled(): bool
    {
        return filter_var($this->settings->get('projects_enabled', true), FILTER_VALIDATE_BOOLEAN);
    }

    public function shopEnabled(): bool
    {
        return filter_var($this->settings->get('shop_enabled', true), FILTER_VALIDATE_BOOLEAN);
    }

    public function galleriesEnabled(): bool
    {
        return filter_var($this->settings->get('galleries_enabled', true), FILTER_VALIDATE_BOOLEAN);
    }

    public function urlIsVisible(?string $url): bool
    {
        $path = $this->path($url);

        if (! $this->projectsEnabled() && $this->matchesAny($path, ['/projects'])) {
            return false;
        }

        if (! $this->shopEnabled() && $this->matchesAny($path, ['/shop', '/cart', '/checkout'])) {
            return false;
        }

        if ($path === '/galleries' && (! $this->projectsEnabled() || ! $this->galleriesEnabled())) {
            return false;
        }

        if (! $this->galleriesEnabled() && $this->matchesAny($path, ['/galleries'])) {
            return false;
        }

        return true;
    }

    private function path(?string $url): string
    {
        if (blank($url) || $url === '#') {
            return '';
        }

        $path = parse_url((string) $url, PHP_URL_PATH) ?: (string) $url;
        $path = '/'.ltrim($path, '/');

        return rtrim($path, '/') ?: '/';
    }

    private function matchesAny(string $path, array $prefixes): bool
    {
        foreach ($prefixes as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return true;
            }
        }

        return false;
    }
}
