<?php

namespace Tests\Unit;

use App\Models\Menu;
use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

class NavigationRendererTest extends TestCase
{
    public function test_header_renderer_keeps_the_existing_two_level_tree(): void
    {
        $menu = $this->menuTree();

        $html = view('components.navigation', [
            'menu' => $menu,
            'variant' => 'header',
            'maxDepth' => 2,
        ])->render();

        $this->assertStringContainsString('data-site-nav', $html);
        $this->assertStringContainsString('آیتم اصلی', $html);
        $this->assertStringContainsString('آیتم فرزند', $html);
        $this->assertStringNotContainsString('آیتم سطح سوم', $html);
        $this->assertStringContainsString('has-children', $html);
    }

    public function test_footer_renderer_keeps_the_existing_root_only_tree(): void
    {
        $menu = $this->menuTree();

        $html = view('components.navigation', [
            'menu' => $menu,
            'variant' => 'footer',
            'maxDepth' => 1,
        ])->render();

        $this->assertStringContainsString('footer-menu', $html);
        $this->assertStringContainsString('آیتم اصلی', $html);
        $this->assertStringNotContainsString('آیتم فرزند', $html);
    }

    private function menuTree(): Menu
    {
        $grandchild = $this->item('آیتم سطح سوم', '/third');
        $child = $this->item('آیتم فرزند', '/child');
        $child->setRelation('children', new Collection([$grandchild]));
        $root = $this->item('آیتم اصلی', '/root');
        $root->setRelation('children', new Collection([$child]));
        $menu = new Menu(['title' => 'منوی تست']);
        $menu->setRelation('rootItems', new Collection([$root]));

        return $menu;
    }

    private function item(string $title, string $url): MenuItem
    {
        $item = new MenuItem([
            'title' => $title,
            'url' => $url,
            'target' => '_self',
            'status' => 'active',
        ]);
        $item->setRelation('children', new Collection);

        return $item;
    }
}
