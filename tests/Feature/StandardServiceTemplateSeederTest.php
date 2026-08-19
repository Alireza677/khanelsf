<?php

namespace Tests\Feature;

use App\CMS\Templates\Recipes\ServiceProfessionalV1Recipe;
use App\CMS\Templates\Recipes\TemplateRecipeCompatibilityValidator;
use App\CMS\Templates\TemplatePublicationValidator;
use App\Models\Service;
use App\Models\Template;
use App\Services\TemplateService;
use Database\Seeders\IndustrialHeaderTemplateSeeder;
use Database\Seeders\StandardProjectTemplateSeeder;
use Database\Seeders\StandardServiceTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StandardServiceTemplateSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_installs_a_published_renderable_default_from_the_registered_recipe(): void
    {
        $this->seed(StandardServiceTemplateSeeder::class);

        $template = $this->standardTemplate();

        $this->assertSame('قالب استاندارد جزئیات خدمت', $template->title);
        $this->assertSame('service_single', $template->type);
        $this->assertSame('published', $template->status);
        $this->assertTrue($template->is_default);
        $this->assertSame(['type' => 'all'], $template->conditions);
        $this->assertTrue($template->hasBlocks());
        $this->assertSame([], app(TemplatePublicationValidator::class)->validate($template->toArray()));
        $this->assertSame(
            collect(app(ServiceProfessionalV1Recipe::class)->blocks())->pluck('type')->all(),
            collect($template->blocks)->pluck('type')->all(),
        );

        $blocks = collect($template->blocks)->keyBy('type');
        $this->assertCount(9, $blocks);
        $this->assertSame('modern-split', data_get($blocks['service_header'], 'data.settings.variant'));
        $this->assertSame('end', data_get($blocks['service_header'], 'data.settings.image_position'));
        $this->assertSame('شروع همکاری', data_get($blocks['service_header'], 'data.settings.primary_action.label'));
        $this->assertSame('custom_url', data_get($blocks['service_header'], 'data.settings.primary_action.action.type'));
        $this->assertSame('#', data_get($blocks['service_header'], 'data.settings.primary_action.action.value'));
        $this->assertSame('مشاوره و گفتگو', data_get($blocks['service_header'], 'data.settings.secondary_action.label'));
        $this->assertSame('icon-cards', data_get($blocks['service_benefits'], 'data.settings.variant'));
        $this->assertSame('connected-steps', data_get($blocks['service_process'], 'data.settings.variant'));
        $this->assertSame('compact-grid', data_get($blocks['service_deliverables'], 'data.settings.variant'));
        $this->assertSame('visual-cards', data_get($blocks['service_projects'], 'data.settings.variant'));
        $this->assertSame('horizontal-gallery', data_get($blocks['service_gallery'], 'data.settings.variant'));
        $this->assertCount(9, collect($template->blocks)->pluck('data.block_id')->unique());
    }

    public function test_delete_then_seed_recreates_the_complete_blueprint_and_second_seed_is_stable(): void
    {
        Template::query()->where('type', 'service_single')->delete();

        $this->seed(StandardServiceTemplateSeeder::class);
        $first = $this->standardTemplate();
        $firstIds = collect($first->blocks)->pluck('data.block_id')->all();
        $firstBlocks = $first->blocks;

        $this->seed(StandardServiceTemplateSeeder::class);
        $second = $this->standardTemplate();

        $this->assertSame(1, Template::query()->where('slug', StandardServiceTemplateSeeder::TEMPLATE_SLUG)->count());
        $this->assertSame($first->getKey(), $second->getKey());
        $this->assertSame($firstIds, collect($second->blocks)->pluck('data.block_id')->all());
        $this->assertSame($firstBlocks, $second->blocks);
        $this->assertSame(
            collect(app(ServiceProfessionalV1Recipe::class)->blocks())->pluck('type')->all(),
            collect($second->blocks)->pluck('type')->all(),
        );
    }

    public function test_seeder_is_idempotent_preserves_admin_content_and_keeps_one_default(): void
    {
        Template::query()->create([
            'title' => 'Default قدیمی',
            'slug' => 'old-service-default',
            'type' => 'service_single',
            'status' => 'published',
            'is_default' => true,
            'conditions' => ['type' => 'all'],
            'blocks' => [['type' => 'service_header', 'data' => []]],
        ]);
        $this->seed(StandardServiceTemplateSeeder::class);
        $template = $this->standardTemplate();
        $blocks = $template->blocks;
        data_set($blocks, '0.data.settings.primary_action.label', 'اقدام اختصاصی مدیر');
        data_set($blocks, '0.data.settings.primary_action.action', [
            'schema_version' => 1, 'type' => 'custom_url', 'value' => '/manager-action', 'open_in_new_tab' => false,
        ]);
        $blocks[] = [
            'type' => 'cta',
            'data' => [
                'block_id' => 'manager-extra-cta', 'schema_version' => 2, 'template' => 'classic',
                'content' => [
                    'eyebrow' => null, 'title' => 'CTA مدیر', 'description' => null,
                    'primary_cta' => ['label' => null, 'action' => null],
                    'secondary_cta' => ['label' => null, 'action' => null], 'media' => ['url' => null],
                ],
                'settings' => data_get($blocks, '8.data.settings'),
            ],
        ];
        $template->update(['title' => 'عنوان ویرایش‌شده مدیر', 'blocks' => $blocks]);
        $blockIds = collect($template->blocks)->pluck('data.block_id')->all();

        $this->seed(StandardServiceTemplateSeeder::class);

        $this->assertSame(1, Template::query()->where('slug', StandardServiceTemplateSeeder::TEMPLATE_SLUG)->count());
        $this->assertSame(1, Template::query()->where('type', 'service_single')->where('is_default', true)->count());
        $this->assertSame('عنوان ویرایش‌شده مدیر', $template->fresh()->title);
        $this->assertSame($blockIds, collect($template->fresh()->blocks)->pluck('data.block_id')->all());
        $this->assertSame('اقدام اختصاصی مدیر', data_get($template->fresh()->blocks, '0.data.settings.primary_action.label'));
        $this->assertSame('/manager-action', data_get($template->fresh()->blocks, '0.data.settings.primary_action.action.value'));
        $this->assertSame('CTA مدیر', data_get($template->fresh()->blocks, '9.data.content.title'));
    }

    public function test_new_service_resolves_and_renders_through_the_default_template(): void
    {
        $this->seed(StandardServiceTemplateSeeder::class);
        $service = Service::query()->create([
            'name' => 'خدمت قابل رندر',
            'slug' => 'renderable-service',
            'status' => Service::STATUS_ACTIVE,
        ]);

        $resolved = app(TemplateService::class)->findTemplateFor('service_single', $service);

        $this->assertTrue($resolved?->is($this->standardTemplate()));
        $this->get(route('services.show', $service->slug))
            ->assertOk()
            ->assertSee('خدمت قابل رندر');
    }

    public function test_recipe_compatibility_and_other_core_template_seeders_remain_valid(): void
    {
        $recipe = app(ServiceProfessionalV1Recipe::class);
        $validator = app(TemplateRecipeCompatibilityValidator::class);

        $this->assertSame([], $validator->validate($recipe));
        $validator->assertCompatible($recipe);

        $this->seed(StandardProjectTemplateSeeder::class);
        $this->seed(IndustrialHeaderTemplateSeeder::class);

        $this->assertDatabaseHas('templates', [
            'slug' => StandardProjectTemplateSeeder::TEMPLATE_SLUG,
            'type' => 'project_single',
        ]);
        $this->assertDatabaseHas('templates', [
            'slug' => 'industrial-header-v1',
            'type' => 'site_header',
        ]);
    }

    private function standardTemplate(): Template
    {
        return Template::query()->where('slug', StandardServiceTemplateSeeder::TEMPLATE_SLUG)->firstOrFail();
    }
}
