<?php

namespace App\Providers;

use App\Media\BlockMediaReferences;
use App\Media\MediaConsumerRegistry;
use App\Media\MediaDependencyService;
use Illuminate\Support\ServiceProvider;

final class MediaAssetServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MediaConsumerRegistry::class);
        $this->app->singleton(BlockMediaReferences::class);
        $this->app->scoped(MediaDependencyService::class);
    }
}
