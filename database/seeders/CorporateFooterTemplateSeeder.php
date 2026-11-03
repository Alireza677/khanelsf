<?php

namespace Database\Seeders;

use App\CMS\Blocks\SiteFooter\SiteFooterDataNormalizer;
use App\CMS\Templates\TemplatePublicationValidator;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Setting;
use App\Models\Template;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CorporateFooterTemplateSeeder extends Seeder
{
    public const INSTALLATION_STATE_KEY = 'king_footer_bootstrap';

    public function run(): void
    {
        // DatabaseSeeder establishes this intent before creating any site data.
        // Missing/deleted templates, menus or selections never imply a fresh installation.
        if (Setting::query()->where('key', self::INSTALLATION_STATE_KEY)->value('value') !== 'pending') {
            return;
        }

        DB::transaction(function (): void {
            $installation = Setting::query()->where('key', self::INSTALLATION_STATE_KEY)->lockForUpdate()->first();
            if ($installation?->value !== 'pending') {
                return;
            }

            // Preserve choices made during an interrupted bootstrap as well.
            if (Template::query()->where('type', 'site_footer')->orWhere('slug', 'corporate-dark-footer-v1')->exists()
                || Setting::query()->whereIn('key', ['footer_template_id', 'footer_menu_id'])->exists()
                || Menu::query()->whereIn('slug', ['footer-menu', 'corporate-footer-company', 'corporate-footer-services', 'corporate-footer-resources'])->exists()) {
                $installation->update(['value' => 'preserved']);

                return;
            }

            $menuId = $this->menu();
            $data = app(SiteFooterDataNormalizer::class)->normalize([
                'block_id' => (string) Str::ulid(),
                'schema_version' => SiteFooterDataNormalizer::SCHEMA_VERSION,
                'template' => 'corporate-dark-v1',
                'content' => [
                    'composition' => [
                        'main' => ['columns' => [
                            $this->column('درباره کسب‌وکار', [
                                $this->element('branding', ['source' => 'site']),
                                $this->element('rich_text', ['source' => 'site']),
                                $this->element('social_links', ['source' => 'site']),
                            ]),
                            $this->column('دسترسی سریع', [
                                $this->element('navigation', ['menu_id' => $menuId, 'depth' => 1]),
                            ]),
                            $this->column('اطلاعات تماس', [
                                $this->element('contact', ['source' => 'site']),
                            ]),
                            $this->column('موقعیت و اعتماد', [
                                $this->element('map', ['mode' => 'static']),
                                $this->element('image'),
                            ]),
                        ]],
                        'bottom' => ['elements' => [
                            $this->element('copyright', ['source' => 'site', 'show_year' => true]),
                            $this->element('legal_links', ['items' => [
                                ['label' => 'حریم خصوصی', 'action' => null],
                                ['label' => 'قوانین و مقررات', 'action' => null],
                            ]]),
                        ]],
                    ],
                ],
                'settings' => ['container' => 'default', 'spacing' => 'comfortable', 'grid' => ['desktop' => 'auto', 'tablet' => 2, 'mobile' => 1]],
            ]);
            $attributes = [
                'slug' => 'corporate-dark-footer-v1',
                'title' => 'فوتر سازمانی تیره',
                'type' => 'site_footer',
                'status' => 'published',
                'is_default' => true,
                'priority' => 0,
                'conditions' => ['type' => 'all'],
                'blocks' => [['type' => 'site_footer', 'data' => $data]],
            ];
            $errors = app(TemplatePublicationValidator::class)->validate($attributes);
            if ($errors !== []) {
                throw ValidationException::withMessages(['blocks' => $errors]);
            }

            $template = Template::query()->create($attributes);
            foreach (['footer_template_id' => $template->getKey(), 'footer_menu_id' => $menuId] as $key => $id) {
                Setting::query()->create(['key' => $key, 'value' => (string) $id, 'group' => 'footer', 'type' => 'select']);
            }
            // Atomic with the preset: even deleting all footer data cannot re-seed it.
            $installation->update(['value' => 'completed']);
        });
    }

    private function column(string $title, array $elements): array
    {
        return ['id' => (string) Str::ulid(), 'title' => $title, 'settings' => ['span' => 1], 'elements' => $elements];
    }

    private function element(string $type, array $content = []): array
    {
        return ['id' => (string) Str::ulid(), 'type' => $type, 'enabled' => true, 'content' => $content];
    }

    private function menu(): int
    {
        $menu = Menu::query()->create(['slug' => 'corporate-footer-company', 'title' => 'دسترسی سریع فوتر', 'location' => null, 'status' => 'active']);
        foreach (['home' => 'خانه', 'about' => 'درباره ما', 'contact' => 'تماس با ما'] as $slug => $title) {
            $page = Page::query()->published()->where('slug', $slug)->first();
            if ($page !== null) {
                MenuItem::query()->create([
                    'menu_id' => $menu->getKey(), 'parent_id' => null, 'title' => $title,
                    'type' => MenuItem::TYPE_PAGE, 'reference_id' => $page->getKey(), 'reference_type' => Page::class,
                    'url' => null, 'source_key' => null, 'target' => '_self',
                    'sort_order' => array_search($slug, ['home', 'about', 'contact'], true) + 1, 'status' => 'active',
                ]);
            }
        }

        return (int) $menu->getKey();
    }
}
