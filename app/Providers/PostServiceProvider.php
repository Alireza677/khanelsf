<?php

namespace App\Providers;

use App\CMS\Navigation\NavigationSource;
use App\CMS\Navigation\NavigationSourceRegistry;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class PostServiceProvider extends ServiceProvider
{
    public function boot(NavigationSourceRegistry $sources): void
    {
        $sources->register(new NavigationSource(
            sourceKey: 'blog.archive',
            label: 'وبلاگ',
            module: null,
            resolver: fn (): ?string => Route::has('blog.index')
                ? route('blog.index', absolute: false)
                : null,
            availability: fn (): bool => Route::has('blog.index'),
        ));
    }
}
