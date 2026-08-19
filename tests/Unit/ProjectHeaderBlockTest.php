<?php

namespace Tests\Unit;

use App\CMS\Blocks\BlockRegistry;
use App\CMS\Blocks\Hero\HeroBlock;
use App\CMS\Blocks\Project\ProjectHeaderBlock;
use App\Models\Project;
use App\Models\ProjectCategory;
use Filament\Forms\Components\Component;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ProjectHeaderBlockTest extends TestCase
{
    public function test_block_is_registered_with_dynamic_project_contract(): void
    {
        $registry = app(BlockRegistry::class);
        $block = $registry->find('project_header');

        $this->assertInstanceOf(ProjectHeaderBlock::class, $block);
        $this->assertContains('dynamic_data', $block->capabilities());
        $this->assertContains('project_context', $block->capabilities());
        $this->assertContains('case_study_header', $block->capabilities());
        $this->assertSame('partials.blocks.project_header', $registry->renderView('project_header'));
    }

    public function test_schema_contains_only_presentation_content_and_settings(): void
    {
        $names = $this->componentNames(
            app(ProjectHeaderBlock::class)->filamentSchema(HeroBlock::CONTEXT_TEMPLATE),
        );

        $this->assertContains('content.eyebrow', $names);
        $this->assertNotContains('content.title', $names);
        $this->assertNotContains('content.subtitle', $names);

        foreach ([
            'variant', 'alignment', 'show_image', 'show_category', 'show_client',
            'show_location', 'show_industry', 'show_project_type', 'show_dates',
            'date_format', 'show_cta', 'cta_type', 'cta_label', 'cta_target',
            'show_secondary_cta', 'secondary_cta_label', 'secondary_cta_target',
        ] as $setting) {
            $this->assertContains("settings.{$setting}", $names);
        }
    }

    public function test_normalizer_is_canonical_idempotent_and_does_not_snapshot_entity_data(): void
    {
        $block = app(ProjectHeaderBlock::class);
        $once = $block->normalize([
            'content' => [
                'eyebrow' => ' مطالعه موردی ',
                'title' => 'Snapshot title',
                'subtitle' => 'Snapshot intro',
            ],
            'settings' => [
                'variant' => 'unknown',
                'alignment' => 'center',
                'show_image' => false,
                'show_cta' => true,
                'cta_type' => 'marketing',
                'cta_label' => 'شروع همکاری',
                'cta_target' => '/contact',
            ],
            'project' => ['title' => 'Snapshot project'],
        ]);

        $this->assertSame(['eyebrow' => 'مطالعه موردی'], $once['content']);
        $this->assertSame('default', $once['settings']['variant']);
        $this->assertSame('center', $once['settings']['alignment']);
        $this->assertFalse($once['settings']['show_image']);
        $this->assertSame('marketing', $once['settings']['cta_type']);
        $this->assertSame('/contact', $once['settings']['cta_target']);
        $this->assertArrayNotHasKey('title', $once['content']);
        $this->assertArrayNotHasKey('project', $once);
        $this->assertSame($once, $block->normalize($once));
    }

    public function test_renderer_uses_project_entity_and_loaded_category(): void
    {
        $project = $this->project([
            'title' => 'پروژه واقعی',
            'excerpt' => 'خلاصه واقعی پروژه',
            'client_name' => 'کارفرمای واقعی',
            'location' => 'تهران',
            'industry' => 'ساختمان',
            'project_type' => 'بازسازی',
        ]);
        $project->setRelation('category', new ProjectCategory(['name' => 'مسکونی', 'slug' => 'residential']));

        $html = $this->render($project, ['settings' => [
            'variant' => 'split',
            'alignment' => 'center',
        ]]);

        $this->assertStringContainsString('<h1 class="block-title">پروژه واقعی</h1>', $html);
        $this->assertStringContainsString('خلاصه واقعی پروژه', $html);
        $this->assertStringContainsString('کارفرمای واقعی', $html);
        $this->assertStringContainsString('تهران', $html);
        $this->assertStringContainsString('ساختمان', $html);
        $this->assertStringContainsString('بازسازی', $html);
        $this->assertStringContainsString('مسکونی', $html);
        $this->assertStringContainsString('dir="rtl"', $html);
        $this->assertStringContainsString('shared-hero--split', $html);
        $this->assertStringContainsString('shared-hero--align-center', $html);
    }

    public function test_renderer_uses_only_the_featured_image_from_loaded_media(): void
    {
        $project = $this->project();
        $project->setRelation('media', collect([
            new class
            {
                public string $collection_name = 'gallery';

                public function getUrl(): string
                {
                    return '/storage/gallery.webp';
                }
            },
            new class
            {
                public string $collection_name = 'featured_image';

                public function getUrl(): string
                {
                    return '/storage/project-featured.webp';
                }
            },
        ]));

        $html = $this->render($project);

        $this->assertStringContainsString('/storage/project-featured.webp', $html);
        $this->assertStringNotContainsString('/storage/gallery.webp', $html);
    }

    public function test_renderer_falls_back_to_first_gallery_image_when_featured_image_is_missing(): void
    {
        $project = $this->project();
        $project->setRelation('media', collect([new class
        {
            public string $collection_name = 'gallery';

            public function getUrl(): string
            {
                return '/storage/gallery-fallback.webp';
            }
        }]));

        $this->assertStringContainsString('/storage/gallery-fallback.webp', $this->render($project));
    }

    public function test_canonical_action_is_preferred_and_invalid_action_fails_closed(): void
    {
        $project = $this->project(['external_url' => 'https://legacy.example.com']);
        $canonical = $this->render($project, ['settings' => [
            'show_cta' => true,
            'cta_label' => 'Legacy',
            'primary_action' => [
                'label' => 'Canonical',
                'action' => ['schema_version' => 1, 'type' => 'custom_url', 'value' => '/contact'],
            ],
        ]]);
        $invalid = $this->render($project, ['settings' => [
            'primary_action' => [
                'label' => 'Unsafe',
                'action' => ['schema_version' => 1, 'type' => 'custom_url', 'value' => 'javascript:alert(1)'],
            ],
        ]]);

        $this->assertStringContainsString('Canonical', $canonical);
        $this->assertStringContainsString('href="/contact"', $canonical);
        $this->assertStringNotContainsString('Legacy', $canonical);
        $this->assertStringNotContainsString('Unsafe', $invalid);
        $this->assertStringNotContainsString('javascript:', $invalid);
    }

    public function test_date_range_uses_case_study_dates_before_legacy_date(): void
    {
        $project = $this->projectWithRawDates([
            'project_started_at' => '2024-01-10',
            'project_completed_at' => '2025-02-20',
            'project_date' => '1999-01-01',
        ]);

        $html = $this->render($project, ['settings' => ['date_format' => 'year']]);

        $this->assertStringContainsString('2024 – 2025', $html);
        $this->assertStringNotContainsString('1999', $html);
    }

    public function test_date_falls_back_to_legacy_project_date_and_hides_when_all_dates_are_empty(): void
    {
        $legacyHtml = $this->render(
            $this->projectWithRawDates(['project_date' => '2021-06-15']),
            ['settings' => ['date_format' => 'year']],
        );
        $emptyHtml = $this->render($this->project());

        $this->assertStringContainsString('2021', $legacyHtml);
        $this->assertStringNotContainsString('تاریخ پروژه', $emptyHtml);
    }

    public function test_project_and_marketing_ctas_have_distinct_sources(): void
    {
        $project = $this->project(['external_url' => 'https://example.com/project']);
        $projectCta = $this->render($project, ['settings' => [
            'show_cta' => true,
            'cta_type' => 'project',
            'cta_label' => 'وب‌سایت پروژه',
            'cta_target' => '/must-not-be-used',
        ]]);
        $marketingCta = $this->render($project, ['settings' => [
            'show_cta' => true,
            'cta_type' => 'marketing',
            'cta_label' => 'شروع همکاری',
            'cta_target' => '/contact',
            'show_secondary_cta' => true,
            'secondary_cta_label' => 'همه پروژه‌ها',
            'secondary_cta_target' => '/projects',
        ]]);

        $this->assertStringContainsString('href="https://example.com/project"', $projectCta);
        $this->assertStringNotContainsString('/must-not-be-used', $projectCta);
        $this->assertStringContainsString('target="_blank"', $projectCta);
        $this->assertStringContainsString('href="/contact"', $marketingCta);
        $this->assertStringNotContainsString('target="_blank"', $marketingCta);
        $this->assertStringContainsString('href="/projects"', $marketingCta);
        $this->assertStringContainsString('همه پروژه‌ها', $marketingCta);
    }

    public function test_visibility_settings_remove_entity_sections_without_changing_entity(): void
    {
        $project = $this->project([
            'client_name' => 'کارفرمای پنهان',
            'location' => 'موقعیت پنهان',
            'industry' => 'صنعت پنهان',
        ]);

        $html = $this->render($project, ['settings' => [
            'show_client' => false,
            'show_location' => false,
            'show_industry' => false,
        ]]);

        $this->assertStringNotContainsString('کارفرمای پنهان', $html);
        $this->assertStringNotContainsString('موقعیت پنهان', $html);
        $this->assertStringNotContainsString('صنعت پنهان', $html);
        $this->assertSame('کارفرمای پنهان', $project->client_name);
    }

    public function test_renderer_does_not_lazy_load_media_or_category(): void
    {
        $project = $this->project();

        $this->assertFalse($project->relationLoaded('media'));
        $this->assertFalse($project->relationLoaded('category'));

        $html = $this->render($project);

        $this->assertStringContainsString('<h1 class="block-title">پروژه آزمایشی</h1>', $html);
        $this->assertFalse($project->relationLoaded('media'));
        $this->assertFalse($project->relationLoaded('category'));
    }

    private function project(array $attributes = []): Project
    {
        return new Project([
            'title' => 'پروژه آزمایشی',
            'excerpt' => null,
            ...$attributes,
        ]);
    }

    private function projectWithRawDates(array $dates): Project
    {
        $project = $this->project();
        $project->setRawAttributes([...$project->getAttributes(), ...$dates]);

        return $project;
    }

    private function render(Project $project, array $data = []): string
    {
        return view('partials.blocks.project_header', [
            'data' => $data,
            'context' => ['kind' => 'single', 'type' => 'project', 'model' => $project],
        ])->render();
    }

    /**
     * @param  array<Component>  $components
     * @return array<string>
     */
    private function componentNames(array $components): array
    {
        return Collection::make($components)
            ->flatMap(function (Component $component): array {
                $names = method_exists($component, 'getName') ? [$component->getName()] : [];
                $children = method_exists($component, 'getChildComponents')
                    ? $component->getChildComponents()
                    : [];

                return [...$names, ...$this->componentNames($children)];
            })
            ->filter()
            ->values()
            ->all();
    }
}
