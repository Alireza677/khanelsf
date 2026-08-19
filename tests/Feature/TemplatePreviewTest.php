<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Project;
use App\Models\Template;
use App\Models\User;
use App\Services\TemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TemplatePreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_template_preview(): void
    {
        $template = $this->template('shop_index', [
            ['type' => 'custom_html', 'data' => ['code' => '<div>Private Preview</div>']],
        ]);

        $this->get(route('admin.preview.templates.show', $template))
            ->assertNotFound();
    }

    public function test_admin_can_preview_product_single_template_with_selected_product(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->published()->create(['title' => 'Preview Product']);
        $template = $this->template('product_single', [
            ['type' => 'template_single_header', 'data' => []],
            ['type' => 'template_add_to_cart', 'data' => ['button_label' => 'Preview Add']],
        ]);

        $this->actingAs($admin)
            ->get(route('admin.preview.templates.show', [
                'template' => $template,
                'context_id' => $product->id,
            ]))
            ->assertOk()
            ->assertSee('Preview Product')
            ->assertSee('content="noindex, nofollow"', false)
            ->assertSee('این صفحه پیش‌نمایش مدیر است و افزودن به سبد خرید غیرفعال است.');
    }

    public function test_admin_can_preview_project_single_template_with_selected_project(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->published()->create(['title' => 'Preview Project']);
        $template = $this->template('project_single', [
            ['type' => 'template_single_header', 'data' => []],
            ['type' => 'template_single_meta', 'data' => []],
        ]);

        $this->actingAs($admin)
            ->get(route('admin.preview.templates.show', [
                'template' => $template,
                'context_id' => $project->id,
            ]))
            ->assertOk()
            ->assertSee('Preview Project')
            ->assertSee('content="noindex, nofollow"', false);
    }

    public function test_admin_can_preview_index_template_without_context_selector(): void
    {
        $admin = User::factory()->admin()->create();
        Product::factory()->published()->create(['title' => 'Preview Grid Product']);
        $template = $this->template('shop_index', [
            ['type' => 'template_archive_header', 'data' => ['title' => 'Preview Shop Index']],
            ['type' => 'template_content_grid', 'data' => []],
        ]);

        $this->actingAs($admin)
            ->get(route('admin.preview.templates.show', $template))
            ->assertOk()
            ->assertSee('Preview Shop Index')
            ->assertSee('Preview Grid Product')
            ->assertSee('content="noindex, nofollow"', false);
    }

    public function test_admin_can_preview_draft_template(): void
    {
        $admin = User::factory()->admin()->create();
        $template = $this->template('shop_index', [
            ['type' => 'custom_html', 'data' => ['code' => '<div>Draft Template Preview</div>']],
        ], ['status' => 'draft']);

        $this->actingAs($admin)
            ->get(route('admin.preview.templates.show', $template))
            ->assertOk()
            ->assertSee('Draft Template Preview')
            ->assertSee('content="noindex, nofollow"', false);
    }

    public function test_template_service_matching_explanation_returns_reason_and_candidates(): void
    {
        $product = Product::factory()->published()->create();
        $template = $this->template('product_single', [
            ['type' => 'custom_html', 'data' => ['code' => '<div>Specific</div>']],
        ], [
            'conditions' => ['type' => 'specific_item', 'item_id' => $product->id],
        ]);

        $explanation = app(TemplateService::class)->explainMatch('product_single', $product);

        $this->assertTrue($template->is($explanation['matched_template']));
        $this->assertSame(3, $explanation['specificity']);
        $this->assertSame('Matched by specific item condition.', $explanation['reason']);
        $this->assertNotEmpty($explanation['candidates']);
    }

    private function template(string $type, array $blocks, array $overrides = []): Template
    {
        return Template::query()->create([
            'title' => $type.' preview template',
            'slug' => str($type.' preview template '.uniqid())->slug()->toString(),
            'type' => $type,
            'status' => 'published',
            'is_default' => true,
            'priority' => 10,
            'conditions' => ['type' => 'all'],
            'blocks' => $blocks,
            ...$overrides,
        ]);
    }
}
