<?php

namespace Tests\Feature;

use App\CMS\Actions\Filament\ActionPicker;
use App\CMS\Blocks\CTA\CTABlock;
use App\CMS\Blocks\Hero\HeroBlock;
use App\Models\Form;
use App\Models\Page;
use App\Models\Product;
use App\Models\Project;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CTARuntimeActionTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('valueActions')]
    public function test_cta_renders_all_value_targets(
        array $action,
        string $expectedHref,
    ): void {
        $html = $this->render($this->cta('Value action', $action));

        $this->assertStringContainsString('>Value action</a>', $html);
        $this->assertStringContainsString('href="'.$expectedHref.'"', $html);
    }

    public static function valueActions(): array
    {
        return [
            'custom URL' => [[
                'type' => 'custom_url',
                'value' => '/contact',
            ], '/contact'],
            'anchor' => [['type' => 'anchor', 'value' => 'contact'], '#contact'],
            'email' => [[
                'type' => 'email',
                'value' => 'info@example.com',
            ], 'mailto:info@example.com'],
            'phone' => [['type' => 'phone', 'value' => '+989121234567'], 'tel:+989121234567'],
        ];
    }

    public function test_cta_renders_temporary_placeholder_through_generic_presentation(): void
    {
        $html = $this->render($this->cta('Temporary CTA', [
            'type' => 'custom_url',
            'value' => ' # ',
        ]));
        $javascript = file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString('href="#"', $html);
        $this->assertStringContainsString('data-action-placeholder', $html);
        $this->assertStringContainsString('>Temporary CTA</a>', $html);
        $this->assertStringContainsString('a[data-action-placeholder][href="#"]', $javascript);
        $this->assertStringContainsString('event.preventDefault()', $javascript);
    }

    public function test_cta_renders_all_entity_targets_through_runtime_resolver(): void
    {
        $page = Page::factory()->published()->create(['slug' => 'cta-runtime-page']);
        $project = Project::factory()->published()->create(['slug' => 'cta-runtime-project']);
        $product = Product::factory()->published()->create(['slug' => 'cta-runtime-product']);
        $service = Service::query()->create([
            'name' => 'CTA Runtime Service',
            'slug' => 'cta-runtime-service',
            'status' => Service::STATUS_ACTIVE,
        ]);

        foreach ([
            'page' => [$page->getKey(), $page->resolveNavigationUrl()],
            'project' => [$project->getKey(), $project->resolveNavigationUrl()],
            'product' => [$product->getKey(), $product->resolveNavigationUrl()],
            'service' => [$service->getKey(), $service->resolveNavigationUrl()],
        ] as $type => [$referenceId, $url]) {
            $html = $this->render($this->cta($type, [
                'type' => $type,
                'reference_id' => $referenceId,
            ]));

            $this->assertStringContainsString('>'.$type.'</a>', $html);
            $this->assertStringContainsString('href="'.$url.'"', $html);
        }
    }

    public function test_cta_form_page_modal_and_legacy_fallback_use_generic_trigger(): void
    {
        $pageForm = $this->form('page', 'cta-page-form');
        $modalForm = $this->form('modal', 'cta-modal-form');

        $page = $this->render($this->cta('Page form', [
            'type' => 'form',
            'reference_id' => $pageForm->getKey(),
            'display' => 'page',
        ]));
        $modal = $this->render($this->cta('Modal form', [
            'type' => 'form',
            'reference_id' => $modalForm->getKey(),
            'display' => 'modal',
        ]));
        $fallback = $this->render($this->cta('Fallback form', [
            'type' => 'form',
            'reference_id' => $modalForm->getKey(),
        ]));

        $this->assertStringContainsString(route('forms.context', $pageForm->slug), $page);
        $this->assertStringNotContainsString('data-form-action-modal-url', $page);
        $this->assertStringContainsString(
            'data-form-action-modal-url="'.route('forms.modal', $modalForm->slug).'"',
            $modal,
        );
        $this->assertStringContainsString('data-form-action-modal-url', $fallback);
        $this->assertStringContainsString('name="_context_page_url" value="/source"', $modal);
        $this->assertStringContainsString('name="_context_block_id"', $modal);
    }

    public function test_invalid_primary_does_not_hide_valid_secondary_and_empty_actions_render_no_buttons(): void
    {
        $mixed = $this->render([
            'schema_version' => 2,
            'template' => 'image',
            'content' => [
                'title' => 'Mixed actions',
                'primary_cta' => [
                    'label' => 'Invalid primary',
                    'action' => ['type' => 'page', 'reference_id' => 999999],
                ],
                'secondary_cta' => [
                    'label' => 'Valid secondary',
                    'action' => ['type' => 'custom_url', 'value' => '/valid'],
                ],
            ],
        ]);
        $empty = $this->render([
            'schema_version' => 2,
            'template' => 'classic',
            'content' => [
                'title' => 'Content remains',
                'primary_cta' => ['label' => 'No destination', 'action' => null],
            ],
        ]);

        $this->assertStringNotContainsString('Invalid primary', $mixed);
        $this->assertStringContainsString('Valid secondary', $mixed);
        $this->assertStringContainsString('href="/valid"', $mixed);
        $this->assertStringContainsString('Content remains', $empty);
        $this->assertStringNotContainsString('No destination', $empty);
    }

    public function test_new_tab_security_and_preview_resolution_are_preserved(): void
    {
        $external = $this->render($this->cta('External', [
            'type' => 'custom_url',
            'value' => 'https://example.com',
            'open_in_new_tab' => true,
        ]));
        $page = Page::factory()->published()->create();
        $preview = $this->render(
            $this->cta('Preview page', [
                'type' => 'page',
                'reference_id' => $page->getKey(),
            ]),
            isPreview: true,
        );

        $this->assertStringContainsString('target="_blank"', $external);
        $this->assertStringContainsString('rel="noopener noreferrer"', $external);
        $this->assertStringContainsString(
            route('admin.preview.pages.show', $page, absolute: false),
            $preview,
        );
    }

    public function test_generic_modal_transport_has_no_cta_specific_selector_contract(): void
    {
        $script = view('partials.action-form-modal-script')->render();
        $form = $this->form('modal', 'multiple-trigger-form');
        $html = $this->render([
            'schema_version' => 2,
            'template' => 'image',
            'content' => [
                'title' => 'Multiple triggers',
                'primary_cta' => [
                    'label' => 'Primary form',
                    'action' => [
                        'type' => 'form',
                        'reference_id' => $form->getKey(),
                        'display' => 'modal',
                    ],
                ],
                'secondary_cta' => [
                    'label' => 'Secondary form',
                    'action' => [
                        'type' => 'form',
                        'reference_id' => $form->getKey(),
                        'display' => 'modal',
                    ],
                ],
            ],
        ]);

        $this->assertStringContainsString('__formActionModalInitialized', $script);
        $this->assertStringContainsString('form[data-form-action-modal-url]', $script);
        $this->assertStringContainsString('[data-form-action-modal-close]', $script);
        $this->assertStringContainsString("new CustomEvent('forms:rendered')", $script);
        $this->assertStringContainsString('returnFocus?.focus()', $script);
        $this->assertStringNotContainsString('data-cta-form-modal-url', $script);
        $this->assertStringNotContainsString('cta-form-modal', $script);
        $this->assertSame(2, substr_count($html, 'data-form-action-modal-url'));
        $this->assertSame(2, substr_count($html, 'name="_token"'));
    }

    public function test_cta_admin_schema_uses_two_reusable_action_pickers(): void
    {
        $pickers = collect(app(CTABlock::class)->filamentSchema(HeroBlock::CONTEXT_PAGE))
            ->filter(fn ($component): bool => $component instanceof ActionPicker)
            ->values();

        $this->assertCount(2, $pickers);
        $this->assertSame(
            ['content.primary_cta.action', 'content.secondary_cta.action'],
            $pickers->map(fn (ActionPicker $picker): string => $picker->getStatePath(false))->all(),
        );

        foreach ($pickers as $picker) {
            $this->assertSame([
                'custom_url',
                'page',
                'project',
                'product',
                'service',
                'form',
                'anchor',
                'email',
                'phone',
            ], array_keys($picker->getTypeOptions()));
        }
    }

    private function cta(string $label, array $action): array
    {
        return [
            'schema_version' => 2,
            'template' => 'classic',
            'content' => [
                'title' => 'CTA Runtime',
                'primary_cta' => compact('label', 'action'),
            ],
        ];
    }

    private function render(
        array $data,
        array $context = ['page_url' => '/source'],
        bool $isPreview = false,
    ): string {
        return view('partials.blocks.cta', compact('data', 'context', 'isPreview'))->render();
    }

    private function form(string $displayMode, string $slug): Form
    {
        return Form::query()->create([
            'name' => 'CTA Runtime Form',
            'slug' => $slug,
            'status' => 'published',
            'display_mode' => $displayMode,
        ]);
    }
}
