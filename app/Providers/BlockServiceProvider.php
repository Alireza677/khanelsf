<?php

namespace App\Providers;

use App\CMS\Blocks\BlockRegistry;
use App\CMS\Blocks\Hero\HeroBlock;
use App\CMS\Blocks\Hero\HeroMediaResolver;
use Illuminate\Support\ServiceProvider;

class BlockServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(HeroMediaResolver::class);

        $this->app->singleton(BlockRegistry::class, fn ($app): BlockRegistry => new BlockRegistry(
            $app,
            ['hero' => HeroBlock::class],
        ));
    }
}
