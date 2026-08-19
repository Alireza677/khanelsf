<?php

namespace App\Providers;

use App\CMS\Navigation\NavigationSource;
use App\CMS\Navigation\NavigationSourceRegistry;
use App\Services\ClientPortalNavigation;
use App\Services\ModuleService;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

class NavigationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            NavigationSourceRegistry::class,
            fn (): NavigationSourceRegistry => new NavigationSourceRegistry,
        );

        $this->app->singleton(ClientPortalNavigation::class);
    }

    public function boot(NavigationSourceRegistry $sources, ModuleService $modules): void
    {
        $sources->register(new NavigationSource(
            sourceKey: 'galleries.archive',
            label: 'گالری پروژه‌ها',
            module: 'projects',
            resolver: fn (): string => route('galleries.index', absolute: false),
            availability: fn (): bool => $modules->projectsEnabled()
                && Route::has('galleries.index'),
        ));
    }
}
