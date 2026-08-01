<?php

namespace App\Providers;

use App\CMS\InternalLinks\Registry\InternalLinkSearchRegistry;
use Illuminate\Support\ServiceProvider;

final class InternalLinkServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            InternalLinkSearchRegistry::class,
            fn (): InternalLinkSearchRegistry => new InternalLinkSearchRegistry,
        );
    }
}
