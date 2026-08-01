<?php

namespace Tests\Unit;

use App\CMS\Navigation\NavigationSource;
use App\CMS\Navigation\NavigationSourceRegistry;
use LogicException;
use PHPUnit\Framework\TestCase;

class NavigationSourceRegistryTest extends TestCase
{
    public function test_sources_can_be_found_listed_and_resolved(): void
    {
        $registry = new NavigationSourceRegistry;
        $source = new NavigationSource(
            sourceKey: 'shop.index',
            label: 'فروشگاه',
            module: 'shop',
            resolver: fn (): string => '/shop',
            availability: fn (): bool => true,
        );

        $registry->register($source);

        $this->assertTrue($registry->exists('shop.index'));
        $this->assertSame($source, $registry->find('shop.index'));
        $this->assertSame(['shop.index' => $source], $registry->all());
        $this->assertSame(['shop.index' => $source], $registry->available());
        $this->assertTrue($registry->isAvailable('shop.index'));
        $this->assertSame('/shop', $registry->resolve('shop.index'));
    }

    public function test_unknown_and_unavailable_sources_fail_closed(): void
    {
        $registry = new NavigationSourceRegistry;
        $registry->register(new NavigationSource(
            sourceKey: 'shop.index',
            label: 'فروشگاه',
            module: 'shop',
            resolver: fn (): string => '/shop',
            availability: fn (): bool => false,
        ));

        $this->assertFalse($registry->exists('unknown.index'));
        $this->assertNull($registry->find('unknown.index'));
        $this->assertFalse($registry->isAvailable('unknown.index'));
        $this->assertNull($registry->resolve('unknown.index'));
        $this->assertFalse($registry->isAvailable('shop.index'));
        $this->assertSame('/shop', $registry->resolve('shop.index'));
        $this->assertSame([], $registry->available());
    }

    public function test_duplicate_source_keys_are_rejected(): void
    {
        $registry = new NavigationSourceRegistry;
        $source = new NavigationSource(
            sourceKey: 'shop.index',
            label: 'فروشگاه',
            module: 'shop',
            resolver: fn (): string => '/shop',
            availability: fn (): bool => true,
        );

        $registry->register($source);

        $this->expectException(LogicException::class);
        $registry->register($source);
    }
}
