<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\Page;
use App\Models\Setting;
use App\Models\Template;
use Illuminate\Database\Seeder;

final class IndustrialHeaderTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $menuId = Menu::query()
            ->where('slug', 'main-menu')
            ->where('status', 'active')
            ->value('id');
        $contactPageId = Page::query()
            ->published()
            ->where('slug', 'contact')
            ->value('id');
        $industrialHeader = Template::query()->firstOrCreate(
            ['slug' => 'industrial-header-v1'],
            [
                'title' => 'هدر صنعتی دو سطحی',
                'type' => 'site_header',
                'status' => 'published',
                'is_default' => false,
                'priority' => 0,
                'conditions' => ['type' => 'all'],
            ],
        );

        if (! $industrialHeader->hasBlocks()) {
            $pageAction = $contactPageId
                ? [
                    'schema_version' => 1,
                    'type' => 'page',
                    'reference_id' => (int) $contactPageId,
                    'open_in_new_tab' => false,
                ]
                : null;

            $industrialHeader->update([
                'blocks' => [[
                    'type' => 'site_header',
                    'data' => [
                        'block_id' => '01JHEADER00000000000000000',
                        'schema_version' => 1,
                        'template' => 'industrial-header-v1',
                        'content' => [
                            'top_actions' => [
                                [
                                    'label' => 'خدمات و پشتیبانی',
                                    'action' => $pageAction,
                                ],
                                [
                                    'label' => 'همکاری با ما',
                                    'action' => $pageAction,
                                ],
                            ],
                            'primary_action' => [
                                'label' => 'محاسبه هزینه ساخت',
                                'action' => $pageAction,
                            ],
                        ],
                        'settings' => [
                            'menu_id' => $menuId ? (int) $menuId : null,
                            'search_enabled' => true,
                            'sticky_enabled' => true,
                            'top_bar_enabled' => true,
                        ],
                    ],
                ]],
            ]);
        }

        Setting::query()->firstOrCreate(
            ['key' => 'header_template_id'],
            [
                'value' => (string) $industrialHeader->getKey(),
                'group' => 'header',
                'type' => 'select',
            ],
        );
    }
}
