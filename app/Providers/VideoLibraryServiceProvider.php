<?php

namespace App\Providers;

use App\CMS\Navigation\NavigationSource;
use App\CMS\Navigation\NavigationSourceRegistry;
use App\Contracts\VideoProviderAdapter;
use App\Services\Video\AparatVideoProvider;
use App\Services\VideoLibrarySettings;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class VideoLibraryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(VideoProviderAdapter::class, AparatVideoProvider::class);
    }

    public function boot(NavigationSourceRegistry $sources, VideoLibrarySettings $settings): void
    {
        $sources->register(new NavigationSource(
            sourceKey: 'videos.archive',
            label: 'کتابخانه ویدئویی',
            module: 'video_library',
            resolver: fn (): ?string => Route::has('videos.index') ? route('videos.index', absolute: false) : null,
            availability: fn (): bool => $settings->enabled() && Route::has('videos.index'),
        ));
    }
}
