<?php

namespace Tests\Unit;

use App\CMS\Navigation\NavigationSourceRegistry;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use App\Models\Project;
use Tests\TestCase;

class MenuItemUrlResolutionTest extends TestCase
{
    public function test_source_item_url_is_resolved_by_the_registry(): void
    {
        $item = new MenuItem([
            'type' => MenuItem::TYPE_SOURCE,
            'source_key' => 'shop.index',
            'url' => '/stored-url-must-not-win',
        ]);

        $this->assertTrue(app(NavigationSourceRegistry::class)->exists('shop.index'));
        $this->assertSame('/shop', $item->resolvedUrl());
    }

    public function test_unknown_source_fails_closed_without_using_stored_url(): void
    {
        $item = new MenuItem([
            'type' => MenuItem::TYPE_SOURCE,
            'source_key' => 'unknown.index',
            'url' => '/stored-url-must-not-win',
        ]);

        $this->assertNull($item->resolvedUrl());
    }

    public function test_internal_item_urls_are_resolved_from_their_current_reference_slug(): void
    {
        $cases = [
            [MenuItem::TYPE_PAGE, new Page(['slug' => 'new-page']), '/new-page'],
            [MenuItem::TYPE_POST, new Post(['slug' => 'new-post']), '/blog/new-post'],
            [MenuItem::TYPE_PRODUCT, new Product(['slug' => 'new-product']), '/shop/new-product'],
            [MenuItem::TYPE_PROJECT, new Project(['slug' => 'new-project']), '/projects/new-project'],
        ];

        foreach ($cases as [$type, $reference, $expectedUrl]) {
            $reference->setAttribute('id', 10);
            $item = new MenuItem([
                'type' => $type,
                'reference_id' => 10,
                'reference_type' => $reference->getMorphClass(),
                'url' => '/stored-old-url',
            ]);
            $item->setRelation('reference', $reference);

            $this->assertSame($expectedUrl, $item->resolvedUrl());
        }
    }

    public function test_special_pages_use_their_canonical_routes(): void
    {
        $home = new Page(['slug' => 'home']);
        $home->setAttribute('id', 1);
        $contact = new Page(['slug' => 'contact']);
        $contact->setAttribute('id', 2);

        $homeItem = $this->referencedItem(MenuItem::TYPE_PAGE, $home, '/old-home');
        $contactItem = $this->referencedItem(MenuItem::TYPE_PAGE, $contact, '/old-contact');

        $this->assertSame('/', $homeItem->resolvedUrl());
        $this->assertSame('/contact', $contactItem->resolvedUrl());
    }

    public function test_only_custom_items_own_their_stored_url(): void
    {
        $custom = new MenuItem([
            'type' => MenuItem::TYPE_CUSTOM_URL,
            'url' => 'https://example.com/custom',
        ]);
        $unreferencedPage = new MenuItem([
            'type' => MenuItem::TYPE_PAGE,
            'url' => '/stored-page',
        ]);
        $missingReference = new MenuItem([
            'type' => MenuItem::TYPE_POST,
            'reference_id' => 99,
            'reference_type' => Post::class,
            'url' => '/stored-post',
        ]);
        $missingReference->setRelation('reference', null);

        $this->assertSame('https://example.com/custom', $custom->resolvedUrl());
        $this->assertNull($unreferencedPage->resolvedUrl());
        $this->assertNull($missingReference->resolvedUrl());
    }

    private function referencedItem(string $type, Page $reference, string $storedUrl): MenuItem
    {
        $item = new MenuItem([
            'type' => $type,
            'reference_id' => $reference->getKey(),
            'reference_type' => $reference->getMorphClass(),
            'url' => $storedUrl,
        ]);
        $item->setRelation('reference', $reference);

        return $item;
    }
}
