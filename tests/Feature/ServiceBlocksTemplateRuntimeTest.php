<?php

namespace Tests\Feature;

use App\CMS\Blocks\BlockEditorHydrator;
use App\CMS\Blocks\BlockRegistry;
use App\CMS\Blocks\Contracts\BlockNormalizer;
use App\Filament\Resources\TemplateResource;
use App\Models\Project;
use App\Models\Service;
use App\Models\Template;
use App\Services\ServiceTemplateContextBuilder;
use App\Services\ServiceTemplateRuntime;
use App\Services\TemplateService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use LogicException;
use ReflectionMethod;
use Tests\TestCase;

class ServiceBlocksTemplateRuntimeTest extends TestCase
{
    use RefreshDatabase;

    private const BLOCKS = [
        'service_header',
        'service_overview',
        'service_benefits',
        'service_process',
        'service_deliverables',
        'service_projects',
        'service_gallery',
        'related_services',
    ];

    public function test_service_blocks_are_registered_with_canonical_idempotent_contracts(): void
    {
        $registry = app(BlockRegistry::class);

        foreach (self::BLOCKS as $key) {
            $definition = $registry->find($key);

            $this->assertSame($key, $definition->key());
            $this->assertSame(1, $definition->version());
            $this->assertSame("partials.blocks.{$key}", $definition->renderView([]));
            $this->assertContains('service_context', $definition->capabilities());
            $this->assertContains('dynamic_data', $definition->capabilities());
            $this->assertInstanceOf(BlockNormalizer::class, $definition);

            $normalized = $definition->normalize([
                'block_id' => 'stable-'.$key,
                'schema_version' => 99,
                'template' => 'unknown',
                'content' => [
                    'title' => '  عنوان بخش  ',
                    'name' => 'نباید ذخیره شود',
                    'overview' => 'نباید ذخیره شود',
                    'benefits' => [['title' => 'نباید ذخیره شود']],
                ],
                'settings' => ['unexpected' => 'discarded'],
                'temporary' => true,
            ]);

            $this->assertSame(
                ['block_id', 'schema_version', 'template', 'content', 'settings'],
                array_keys($normalized),
            );
            $this->assertSame('stable-'.$key, $normalized['block_id']);
            $this->assertSame(1, $normalized['schema_version']);
            $this->assertSame('default', $normalized['template']);
            $this->assertArrayNotHasKey('name', $normalized['content']);
            $this->assertArrayNotHasKey('overview', $normalized['content']);
            $this->assertArrayNotHasKey('benefits', $normalized['content']);
            $this->assertSame($normalized, $definition->normalize($normalized));
        }

        $hydrated = app(BlockEditorHydrator::class)->hydrate(array_map(
            fn (string $key): array => ['type' => $key, 'data' => []],
            self::BLOCKS,
        ));

        $this->assertCount(8, collect($hydrated)->pluck('data.block_id')->filter()->unique());
    }

    public function test_template_editor_filters_entity_blocks_by_target_and_keeps_common_blocks(): void
    {
        $method = new ReflectionMethod(TemplateResource::class, 'blockDefinitions');
        $serviceKeys = collect($method->invoke(null, 'service_single'))
            ->map(fn ($block): string => $block->getName());
        $productKeys = collect($method->invoke(null, 'product_single'))
            ->map(fn ($block): string => $block->getName());
        $projectKeys = collect($method->invoke(null, 'project_single'))
            ->map(fn ($block): string => $block->getName());

        foreach (self::BLOCKS as $key) {
            $this->assertTrue($serviceKeys->contains($key));
            $this->assertFalse($productKeys->contains($key));
            $this->assertFalse($projectKeys->contains($key));
        }

        $this->assertTrue($serviceKeys->contains('cta'));
        $this->assertTrue($serviceKeys->contains('form'));
        $this->assertFalse($serviceKeys->contains('product_header'));
        $this->assertFalse($serviceKeys->contains('project_header'));
        $this->assertTrue($productKeys->contains('product_header'));
        $this->assertTrue($projectKeys->contains('project_header'));
        $this->assertArrayHasKey('service_single', Template::TYPES);
        $this->assertContains('service_single', Template::ITEM_TEMPLATE_TYPES);
    }

    public function test_service_blocks_render_canonical_context_without_additional_queries(): void
    {
        Storage::fake('public');
        $service = $this->service([
            'name' => 'طراحی سازه',
            'excerpt' => 'خلاصه خدمت',
            'overview' => '<p>معرفی کامل خدمت</p>',
            'benefits' => [['title' => 'سرعت', 'description' => 'اجرای سریع', 'icon' => 'flash']],
            'process' => [['title' => 'تحلیل', 'description' => 'بررسی نیاز']],
            'deliverables' => [['title' => 'نقشه اجرایی', 'description' => 'فایل نهایی']],
            'icon' => 'building',
        ]);
        $project = Project::factory()->published()->create([
            'title' => 'پروژه مرتبط',
            'excerpt' => 'خلاصه پروژه',
        ]);
        $service->projects()->attach($project);
        $this->addMedia($service, 'featured.jpg', 'featured_image');
        $this->addMedia($service, 'gallery.jpg', 'gallery');

        $context = app(ServiceTemplateContextBuilder::class)->build($service);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $html = collect(self::BLOCKS)
            ->map(fn (string $key): string => view("partials.blocks.{$key}", [
                'data' => [],
                'context' => $context,
            ])->render())
            ->implode("\n");

        $this->assertSame([], DB::getQueryLog());
        $this->assertStringContainsString('طراحی سازه', $html);
        $this->assertStringContainsString('خلاصه خدمت', $html);
        $this->assertStringContainsString('معرفی کامل خدمت', $html);
        $this->assertStringContainsString('سرعت', $html);
        $this->assertStringContainsString('تحلیل', $html);
        $this->assertStringContainsString('>1<', $html);
        $this->assertStringContainsString('نقشه اجرایی', $html);
        $this->assertStringContainsString('پروژه مرتبط', $html);
        $this->assertStringContainsString('gallery.jpg', $html);
        $this->assertStringNotContainsString('Related Services', $html);
    }

    public function test_header_and_empty_blocks_are_fail_safe(): void
    {
        $empty = [
            'content' => [
                'name' => 'خدمت بدون تصویر',
                'excerpt' => null,
                'overview' => null,
                'benefits' => [],
                'process' => [],
                'deliverables' => [],
                'icon' => null,
            ],
            'media' => ['featured' => null, 'gallery' => collect(), 'seo_image' => null],
            'projects' => collect(),
            'relatedServices' => collect(),
        ];

        $header = view('partials.blocks.service_header', ['data' => [], 'context' => $empty])->render();
        $this->assertStringContainsString('خدمت بدون تصویر', $header);
        $this->assertStringNotContainsString('<img', $header);

        foreach (array_slice(self::BLOCKS, 1) as $key) {
            $this->assertSame('', trim(view("partials.blocks.{$key}", [
                'data' => [],
                'context' => $empty,
            ])->render()));
        }
    }

    public function test_professional_variants_wrap_arbitrary_dynamic_item_counts(): void
    {
        foreach ([1, 2, 3, 4, 6, 8, 10] as $count) {
            $html = view('partials.blocks.service_benefits', [
                'data' => ['settings' => ['variant' => 'icon-cards', 'columns' => 3]],
                'context' => ['content' => ['benefits' => $this->items($count)]],
            ])->render();

            $this->assertSame($count, substr_count($html, '<article class="service-card">'));
            $this->assertStringContainsString('service-grid--icon-cards', $html);
        }

        foreach ([2, 3, 4, 6] as $count) {
            $html = view('partials.blocks.service_process', [
                'data' => ['settings' => ['variant' => 'connected-steps', 'layout' => 'horizontal']],
                'context' => ['content' => ['process' => $this->items($count)]],
            ])->render();

            $this->assertSame($count, substr_count($html, '<li>'));
            $this->assertStringContainsString('service-process--connected-steps', $html);
            $this->assertStringContainsString('>'.$count.'<', $html);
        }

        foreach ([1, 3, 5, 7] as $count) {
            $html = view('partials.blocks.service_deliverables', [
                'data' => ['settings' => ['variant' => 'compact-grid', 'style' => 'cards', 'columns' => 3]],
                'context' => ['content' => ['deliverables' => $this->items($count)]],
            ])->render();

            $this->assertSame($count, substr_count($html, '<li class="service-card">'));
            $this->assertStringContainsString('service-deliverables--compact-grid', $html);
        }
    }

    public function test_visual_project_cards_keep_one_two_three_and_five_items_in_the_same_grid(): void
    {
        Storage::fake('public');

        foreach ([1, 2, 3, 5] as $count) {
            $projects = Project::factory()->published()->count($count)->create([
                'excerpt' => str_repeat('خلاصه پروژه ', 12),
            ])->load(['category', 'media']);

            DB::flushQueryLog();
            DB::enableQueryLog();

            $html = view('partials.blocks.service_projects', [
                'data' => ['settings' => ['variant' => 'visual-cards', 'columns' => 3]],
                'context' => ['projects' => $projects],
            ])->render();

            $this->assertSame([], DB::getQueryLog());
            $this->assertSame($count, substr_count($html, 'class="blog-card service-project-card"'));
            $this->assertSame($count, substr_count($html, 'class="blog-card__view-link"'));
            $this->assertStringContainsString('service-projects--visual-cards', $html);
            $this->assertStringContainsString('service-grid--3', $html);
        }

        $empty = view('partials.blocks.service_projects', [
            'data' => ['settings' => ['variant' => 'visual-cards', 'columns' => 3]],
            'context' => ['projects' => collect()],
        ])->render();

        $this->assertSame('', trim($empty));
    }

    public function test_service_icons_use_shared_frontend_renderer_without_printing_icon_keys(): void
    {
        $context = [
            'content' => [
                'name' => 'Iconsax Service',
                'icon' => 'icon-arrow-circle-left',
                'benefits' => [
                    ['title' => 'First', 'icon' => 'icon-activity'],
                    ['title' => 'Second', 'icon' => 'icon-airdrop'],
                    ['title' => 'No icon', 'icon' => null],
                ],
            ],
            'media' => ['featured' => null],
        ];
        $header = view('partials.blocks.service_header', ['data' => [], 'context' => $context])->render();
        $benefits = view('partials.blocks.service_benefits', ['data' => [], 'context' => $context])->render();
        $legacy = view('partials.blocks._icon', ['icon' => 'heroicon-o-star'])->render();

        $this->assertStringContainsString('<i class="icon-arrow-circle-left"', $header);
        $this->assertStringNotContainsString('>icon-arrow-circle-left<', $header);
        $this->assertStringContainsString('<i class="icon-activity"', $benefits);
        $this->assertStringContainsString('<i class="icon-airdrop"', $benefits);
        $this->assertStringNotContainsString('>icon-activity<', $benefits);
        $this->assertSame(2, substr_count($benefits, 'class="service-card__icon"'));
        $this->assertStringContainsString('<svg', $legacy);
        $this->assertStringNotContainsString('>heroicon-o-star<', $legacy);
        $this->assertSame('', trim(view('partials.blocks._icon', ['icon' => null])->render()));
    }

    public function test_service_template_resolution_supports_specific_and_default_assignments(): void
    {
        $service = $this->service();
        $default = $this->template('Default service template');
        $specific = $this->template('Specific service template', [
            'is_default' => false,
            'conditions' => ['type' => 'specific_item', 'item_id' => $service->getKey()],
        ]);

        $this->assertTrue(
            $specific->is(app(TemplateService::class)->findTemplateFor('service_single', $service)),
        );

        $specific->delete();

        $this->assertTrue(
            $default->is(app(TemplateService::class)->findTemplateFor('service_single', $service)),
        );
    }

    public function test_runtime_renders_selected_template_and_preview_forces_noindex(): void
    {
        $service = $this->service([
            'name' => 'Runtime Service',
            'overview' => '<p>Runtime overview</p>',
        ]);
        $template = $this->template('Runtime template', [
            'blocks' => [
                ['type' => 'service_header', 'data' => []],
                ['type' => 'service_overview', 'data' => []],
            ],
        ]);
        $runtime = app(ServiceTemplateRuntime::class);

        $production = $runtime->render($service)->render();
        $preview = $runtime->render($service, preview: true, template: $template)->render();

        $this->assertStringContainsString('Runtime Service', $production);
        $this->assertStringContainsString('Runtime overview', $production);
        $this->assertStringContainsString(
            '<meta name="robots" content="noindex, nofollow">',
            $preview,
        );
    }

    public function test_runtime_has_controlled_missing_template_and_public_lifecycle_behavior(): void
    {
        $active = $this->service(['name' => 'Legacy Active', 'slug' => 'legacy-active']);
        $published = $this->service([
            'name' => 'Modern Published',
            'slug' => 'modern-published',
            'status' => Service::STATUS_PUBLISHED,
        ]);
        $draft = $this->service([
            'name' => 'Draft Service',
            'slug' => 'draft-service',
            'status' => Service::STATUS_DRAFT,
        ]);
        $this->template('Public runtime template');
        $runtime = app(ServiceTemplateRuntime::class);

        $this->assertStringContainsString('Legacy Active', $runtime->renderPublishedSlug($active->slug)->render());
        $this->assertStringContainsString('Modern Published', $runtime->renderPublishedSlug($published->slug)->render());

        try {
            $runtime->renderPublishedSlug($draft->slug);
            $this->fail('Draft service must not enter the public runtime.');
        } catch (ModelNotFoundException) {
            $this->assertTrue(true);
        }

        Template::query()->delete();

        $this->expectException(LogicException::class);
        $runtime->render($active);
    }

    private function service(array $overrides = []): Service
    {
        return Service::query()->create([
            'name' => 'Service '.uniqid(),
            'slug' => 'service-'.uniqid(),
            'status' => Service::STATUS_ACTIVE,
            ...$overrides,
        ]);
    }

    private function template(string $title, array $overrides = []): Template
    {
        return Template::query()->create([
            'title' => $title,
            'slug' => str($title.' '.uniqid())->slug()->toString(),
            'type' => 'service_single',
            'status' => 'published',
            'priority' => 0,
            'is_default' => true,
            'conditions' => ['type' => 'all'],
            'blocks' => [['type' => 'service_header', 'data' => []]],
            ...$overrides,
        ]);
    }

    private function addMedia(Service $service, string $fileName, string $collection): void
    {
        $service->media()->create([
            'collection_name' => $collection,
            'name' => pathinfo($fileName, PATHINFO_FILENAME),
            'file_name' => $fileName,
            'mime_type' => 'image/jpeg',
            'disk' => 'public',
            'conversions_disk' => 'public',
            'size' => 1,
            'manipulations' => [],
            'custom_properties' => [],
            'generated_conversions' => [],
            'responsive_images' => [],
            'order_column' => 1,
        ]);
    }

    private function items(int $count): array
    {
        return collect(range(1, $count))
            ->map(fn (int $index): array => [
                'title' => "آیتم {$index}",
                'description' => $index % 2 === 0 ? null : "توضیح پویا {$index}",
                'icon' => "icon-{$index}",
            ])
            ->all();
    }
}
