<?php

namespace Tests\Unit;

use App\CMS\Navigation\NavigationSource;
use App\CMS\Navigation\NavigationSourceRegistry;
use App\Models\MenuItem;
use App\Services\ModuleService;
use App\Services\NavigationSourceVisibility;
use Mockery;
use PHPUnit\Framework\TestCase;

class NavigationSourceVisibilityTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_only_available_registered_sources_are_listed(): void
    {
        $registry = new NavigationSourceRegistry;
        $registry->register($this->source('shop.index', false));
        $registry->register($this->source('blog.index', true));
        $modules = Mockery::mock(ModuleService::class);

        $sources = collect((new NavigationSourceVisibility($registry, $modules))->visibleSources())
            ->pluck('source_key');

        $this->assertNotContains('shop.index', $sources);
        $this->assertContains('blog.index', $sources);
    }

    public function test_renderer_requires_active_and_available_destinations(): void
    {
        $registry = new NavigationSourceRegistry;
        $registry->register($this->source('shop.index', false));
        $modules = Mockery::mock(ModuleService::class);
        $modules->shouldReceive('urlIsVisible')->with('/hidden')->andReturnFalse();
        $visibility = new NavigationSourceVisibility($registry, $modules);

        $disabledSource = new MenuItem([
            'type' => MenuItem::TYPE_SOURCE,
            'source_key' => 'shop.index',
            'status' => 'active',
        ]);
        $unknownSource = new MenuItem([
            'type' => MenuItem::TYPE_SOURCE,
            'source_key' => 'unknown.index',
            'status' => 'active',
        ]);
        $incompleteSource = new MenuItem([
            'type' => MenuItem::TYPE_SOURCE,
            'status' => 'active',
        ]);
        $inactiveCustomUrl = new MenuItem([
            'type' => MenuItem::TYPE_CUSTOM_URL,
            'url' => '/about',
            'status' => 'inactive',
        ]);
        $hiddenCustomUrl = new MenuItem([
            'type' => MenuItem::TYPE_CUSTOM_URL,
            'url' => '/hidden',
            'status' => 'active',
        ]);

        $this->assertFalse($visibility->menuItemIsVisible($disabledSource));
        $this->assertFalse($visibility->menuItemIsVisible($unknownSource));
        $this->assertFalse($visibility->menuItemIsVisible($incompleteSource));
        $this->assertFalse($visibility->menuItemIsVisible($inactiveCustomUrl));
        $this->assertFalse($visibility->menuItemIsVisible($hiddenCustomUrl));
    }

    private function source(string $sourceKey, bool $available): NavigationSource
    {
        return new NavigationSource(
            sourceKey: $sourceKey,
            label: $sourceKey,
            module: null,
            resolver: fn (): string => '/'.str_replace('.', '/', $sourceKey),
            availability: fn (): bool => $available,
        );
    }
}
