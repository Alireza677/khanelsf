<?php

namespace App\Providers;

use App\CMS\Navigation\NavigationSourceRegistry;
use Illuminate\Support\ServiceProvider;

class NavigationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            NavigationSourceRegistry::class,
            fn (): NavigationSourceRegistry => new NavigationSourceRegistry,
        );
    }
}
