<?php

namespace Tests\Feature;

use App\CMS\Templates\TemplatePublicationValidator;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Template;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\StandardProductTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class StandardProductTemplateBootstrapTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_database_bootstrap_installs_once_and_never_recreates_a_deleted_template(): void
    {
        $this->seed(DatabaseSeeder::class);
        $template = Template::where('slug', StandardProductTemplateSeeder::TEMPLATE_SLUG)->sole();
        $this->assertSame('published', $template->status);
        $this->assertTrue($template->is_default);
        $this->assertSame([], app(TemplatePublicationValidator::class)->validate($template->toArray()));
        $this->assertSame('completed', Setting::where('key', StandardProductTemplateSeeder::INSTALLATION_STATE_KEY)->value('value'));
        $template->update(['title' => 'انتخاب مدیر', 'is_default' => false]);
        $before = $template->fresh()->getAttributes();
        $this->seed(DatabaseSeeder::class);
        $this->seed(StandardProductTemplateSeeder::class);
        $this->assertSame($before, $template->fresh()->getAttributes());
        $template->delete();
        $this->seed(StandardProductTemplateSeeder::class);
        $this->assertSame(0, Template::where('type', 'product_single')->count());
    }

    public function test_existing_site_without_product_template_is_not_treated_as_fresh(): void
    {
        Product::factory()->create();
        (new ReflectionMethod(DatabaseSeeder::class, 'prepareFooterBootstrap'))->invoke(new DatabaseSeeder);
        $this->seed(StandardProductTemplateSeeder::class);
        $this->assertDatabaseMissing('settings', ['key' => StandardProductTemplateSeeder::INSTALLATION_STATE_KEY]);
        $this->assertDatabaseCount('templates', 0);
    }

    public function test_interrupted_bootstrap_preserves_any_admin_product_template_and_default(): void
    {
        Setting::create(['key' => StandardProductTemplateSeeder::INSTALLATION_STATE_KEY, 'value' => 'pending', 'group' => 'system', 'type' => 'text']);
        $template = Template::create(['title' => 'قالب انتخاب مدیر', 'slug' => 'admin-product', 'type' => 'product_single', 'status' => 'draft', 'is_default' => true, 'blocks' => []]);
        $before = $template->fresh()->getAttributes();
        $this->seed(StandardProductTemplateSeeder::class);
        $this->assertSame($before, $template->fresh()->getAttributes());
        $this->assertDatabaseCount('templates', 1);
        $this->assertSame('preserved', Setting::where('key', StandardProductTemplateSeeder::INSTALLATION_STATE_KEY)->value('value'));
    }
}
