<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Setting;
use App\Models\Template;
use Illuminate\Database\Seeder;

final class CorporateFooterTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $menus = [
            $this->menu('corporate-footer-company', 'درباره شرکت', [
                $this->pageItem('درباره ما', 'about', 1),
                $this->pageItem('تماس با ما', 'contact', 2),
            ]),
            $this->menu('corporate-footer-services', 'خدمات و محصولات', [
                $this->sourceItem('خدمات', 'services.archive', 1),
                $this->sourceItem('فروشگاه', 'shop.index', 2),
            ]),
            $this->menu('corporate-footer-resources', 'دسترسی سریع', [
                $this->sourceItem('وبلاگ', 'blog.archive', 1),
                $this->sourceItem('گالری پروژه‌ها', 'galleries.archive', 2),
            ]),
        ];
        $template = Template::query()->firstOrCreate(
            ['slug' => 'corporate-dark-footer-v1'],
            [
                'title' => 'فوتر سازمانی تیره',
                'type' => 'site_footer',
                'status' => 'published',
                'is_default' => false,
                'priority' => 0,
                'conditions' => ['type' => 'all'],
            ],
        );

        if (! $template->hasBlocks()) {
            $template->update([
                'blocks' => [[
                    'type' => 'site_footer',
                    'data' => [
                        'block_id' => '01JFOOTER00000000000000000',
                        'schema_version' => 1,
                        'template' => 'corporate-dark-v1',
                        'content' => [
                            'menu_columns' => [
                                ['title' => 'درباره شرکت', 'menu_id' => $menus[0]],
                                ['title' => 'خدمات و محصولات', 'menu_id' => $menus[1]],
                                ['title' => 'دسترسی سریع', 'menu_id' => $menus[2]],
                            ],
                            'contact_title' => 'ارتباط با ما',
                            'about_title' => 'درباره ما',
                            'about_text' => null,
                            'badges_title' => 'مجوزها و نمادهای اعتماد',
                            'badges' => [],
                            'privacy_label' => 'حریم خصوصی',
                            'privacy_action' => null,
                            'terms_label' => 'قوانین و مقررات',
                            'terms_action' => null,
                        ],
                    ],
                ]],
            ]);
        }

        Setting::query()->firstOrCreate(
            ['key' => 'footer_template_id'],
            [
                'value' => (string) $template->getKey(),
                'group' => 'footer',
                'type' => 'select',
            ],
        );
    }

    /** @param array<int, array<string, mixed>|null> $items */
    private function menu(string $slug, string $title, array $items): int
    {
        $menu = Menu::query()->firstOrCreate(
            ['slug' => $slug],
            ['title' => $title, 'location' => null, 'status' => 'active'],
        );

        foreach (array_filter($items) as $item) {
            MenuItem::query()->updateOrCreate(
                ['menu_id' => $menu->getKey(), 'title' => $item['title']],
                [...$item, 'menu_id' => $menu->getKey()],
            );
        }

        return (int) $menu->getKey();
    }

    /** @return array<string, mixed>|null */
    private function pageItem(string $title, string $slug, int $sortOrder): ?array
    {
        $page = Page::query()->published()->where('slug', $slug)->first();

        return $page ? [
            'title' => $title,
            'type' => MenuItem::TYPE_PAGE,
            'reference_id' => $page->getKey(),
            'reference_type' => Page::class,
            'url' => null,
            'source_key' => null,
            'target' => '_self',
            'sort_order' => $sortOrder,
            'status' => 'active',
        ] : null;
    }

    /** @return array<string, mixed> */
    private function sourceItem(string $title, string $sourceKey, int $sortOrder): array
    {
        return [
            'title' => $title,
            'type' => MenuItem::TYPE_SOURCE,
            'source_key' => $sourceKey,
            'reference_id' => null,
            'reference_type' => null,
            'url' => null,
            'target' => '_self',
            'sort_order' => $sortOrder,
            'status' => 'active',
        ];
    }
}
