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
        $template->update(['title' => 'عنوان ویرایش‌شده مدیر']);
        $blockIds = collect($template->blocks)->pluck('data.block_id')->all();

        $this->seed(StandardServiceTemplateSeeder::class);

        $this->assertSame(1, Template::query()->where('slug', StandardServiceTemplateSeeder::TEMPLATE_SLUG)->count());
        $this->assertSame(1, Template::query()->where('type', 'service_single')->where('is_default', true)->count());
        $this->assertSame('عنوان ویرایش‌شده مدیر', $template->fresh()->title);
        $this->assertSame($blockIds, collect($template->fresh()->blocks)->pluck('data.block_id')->all());
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
