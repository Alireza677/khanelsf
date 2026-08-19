<?php

namespace Tests\Feature;

use App\CMS\Blocks\CTA\CTADataNormalizer;
use App\Filament\Resources\PageResource\Pages\EditPage;
use App\Filament\Resources\TemplateResource\Pages\EditTemplate;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Lead;
use App\Models\Page;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CTAV2Test extends TestCase
{
    use RefreshDatabase;

    private const ID = '01ARZ3NDEKTSV4RRFFQ69G5FAV';

    public function test_legacy_and_v2_cta_render_with_identical_output(): void
    {
        foreach ([$this->legacyCta('classic'), $this->legacyCta('image')] as $legacy) {
            $v2 = app(CTADataNormalizer::class)->normalize($legacy);

            $this->assertSame($this->render($legacy), $this->render($v2));
        }

        $html = $this->render($this->legacyCta('image'));
        $this->assertStringContainsString('block-cta-image', $html);
        $this->assertStringContainsString('href="/primary"', $html);
        $this->assertStringContainsString('href="/secondary"', $html);
        $this->assertStringContainsString('background.jpg', $html);
    }

    public function test_page_editor_hydrates_legacy_cta_to_nested_v2_without_writing_on_open(): void
    {
        config()->set('cms.hero_v2_editor', false);
        $this->actingAs(User::factory()->create());
        $page = Page::factory()->create(['blocks' => [['type' => 'cta', 'data' => $this->legacyCta('image')]]]);
        $before = $page->blocks;

        $component = Livewire::test(EditPage::class, ['record' => $page->getRouteKey()]);
        $uuid = array_key_first($component->get('data')['blocks']);

        $component
            ->assertSet("data.blocks.{$uuid}.data.schema_version", 2)
            ->assertSet("data.blocks.{$uuid}.data.content.title", 'CTA title')
            ->assertSet("data.blocks.{$uuid}.data.content.media.url", '/background.jpg');
        $this->assertSame($before, $page->fresh()->blocks);
    }

    public function test_existing_save_boundary_persists_canonical_cta_and_stable_identity(): void
    {
        config()->set('cms.hero_v2_editor', false);
        $this->actingAs(User::factory()->create());
        $page = Page::factory()->create(['blocks' => [['type' => 'cta', 'data' => $this->legacyCta('classic')]]]);
        $component = Livewire::test(EditPage::class, ['record' => $page->getRouteKey()]);
        $uuid = array_key_first($component->get('data')['blocks']);

        $component->set("data.blocks.{$uuid}.data.content.title", 'Edited CTA')->call('save')->assertHasNoFormErrors();

        $saved = $page->fresh()->blocks[0]['data'];
        $this->assertSame(['block_id', 'schema_version', 'template', 'content', 'settings'], array_keys($saved));
        $this->assertSame(self::ID, $saved['block_id']);
        $this->assertSame(2, $saved['schema_version']);
        $this->assertSame('Edited CTA', $saved['content']['title']);
        $this->assertArrayNotHasKey('button_label', $saved);
        $this->assertStringContainsString('Edited CTA', $this->render($saved));
    }

    public function test_template_changes_preserve_hidden_cta_content(): void
    {
        $this->actingAs(User::factory()->create());
        $page = Page::factory()->create(['blocks' => [['type' => 'cta', 'data' => $this->legacyCta('image')]]]);
        $component = Livewire::test(EditPage::class, ['record' => $page->getRouteKey()]);
        $uuid = array_key_first($component->get('data')['blocks']);

        $component
            ->assertSet("data.blocks.{$uuid}.data.content.secondary_cta.label", 'Secondary')
            ->set("data.blocks.{$uuid}.data.template", 'classic')
            ->assertSet("data.blocks.{$uuid}.data.content.secondary_cta.label", 'Secondary')
            ->set("data.blocks.{$uuid}.data.template", 'image')
            ->assertSet("data.blocks.{$uuid}.data.content.secondary_cta.label", 'Secondary')
            ->call('save')
            ->assertHasNoFormErrors();

        $saved = $page->fresh()->blocks[0]['data'];

        $this->assertSame('image', $saved['template']);
        $this->assertSame('Secondary', $saved['content']['secondary_cta']['label']);
        $this->assertSame('/background.jpg', $saved['content']['media']['url']);
    }

    public function test_template_editor_hydrates_and_saves_the_same_canonical_contract(): void
    {
        config()->set('cms.hero_v2_editor', false);
        $this->actingAs(User::factory()->create());
        $template = Template::query()->create([
            'title' => 'CTA Template',
            'slug' => 'cta-template',
            'type' => 'page',
            'status' => 'draft',
            'blocks' => [['type' => 'cta', 'data' => $this->legacyCta('image')]],
        ]);
        $before = $template->blocks;

        $component = Livewire::test(EditTemplate::class, ['record' => $template->getRouteKey()]);
        $uuid = array_key_first($component->get('data')['blocks']);

        $component
            ->assertSet("data.blocks.{$uuid}.data.schema_version", 2)
            ->assertSet("data.blocks.{$uuid}.data.content.title", 'CTA title');
        $this->assertSame($before, $template->fresh()->blocks);

        $component->set("data.blocks.{$uuid}.data.content.title", 'Template CTA')->call('save')->assertHasNoFormErrors();
        $saved = $template->fresh()->blocks[0]['data'];

        $this->assertSame(self::ID, $saved['block_id']);
        $this->assertSame(2, $saved['schema_version']);
        $this->assertSame('Template CTA', $saved['content']['title']);
        $this->assertArrayNotHasKey('cta_template', $saved);
    }

    public function test_page_form_action_captures_attribution_then_redirects_to_a_clean_url(): void
    {
        $form = $this->form('page');
        $page = Page::factory()->published()->create();
        $html = $this->render([
            'block_id' => self::ID,
            'schema_version' => 2,
            'template' => 'classic',
            'content' => [
                'title' => 'CTA title',
                'primary_cta' => [
                    'label' => 'Open form',
                    'action' => ['type' => 'form', 'form_id' => $form->getKey()],
                ],
            ],
        ], ['page_id' => $page->getKey(), 'page_url' => '/home']);

        $this->assertStringContainsString('action="'.route('forms.context', $form->slug).'"', $html);
        $this->assertStringNotContainsString('_context_page_id=', $html);
        $this->assertStringNotContainsString('_context_block_id=', $html);
        $this->assertStringContainsString('name="_context_page_id" value="'.$page->getKey().'"', $html);
        $this->assertStringContainsString('name="_context_block_id" value="'.self::ID.'"', $html);
        $this->assertStringNotContainsString('data-form-action-modal-url', $html);

        $this->post(route('forms.context', $form->slug), [
            '_context_page_id' => $page->getKey(),
            '_context_page_url' => '/home',
            '_context_block_id' => self::ID,
        ])->assertRedirect(route('forms.show', $form->slug));

        $this->get(route('forms.show', $form->slug))
            ->assertOk()
            ->assertDontSee('name="_context_page_id"', false)
            ->assertDontSee('name="_context_block_id"', false);

        $this->post(route('forms.submit', $form->slug), ['email' => 'page@example.com']);
        $submission = FormSubmission::query()->sole();
        $this->assertSame($page->getKey(), $submission->page_id);
        $this->assertSame('/home', $submission->page_url);
        $this->assertSame(self::ID, $submission->block_id);
        $this->assertNull(session("forms.attribution.{$form->getKey()}"));
    }

    public function test_modal_form_action_is_lazy_and_fragment_contains_cta_attribution(): void
    {
        $form = $this->form('page');
        $page = Page::factory()->published()->create(['slug' => 'modal-source']);
        $page->update(['blocks' => [[
            'type' => 'cta',
            'data' => [
                'block_id' => self::ID,
                'schema_version' => 2,
                'template' => 'classic',
                'content' => [
                    'title' => 'CTA title',
                    'primary_cta' => [
                        'label' => 'Open modal',
                        'action' => ['type' => 'form', 'form_id' => $form->getKey(), 'display' => 'modal'],
                    ],
                ],
            ],
        ]]]);

        $response = $this->get(route('pages.show', $page->slug))->assertOk();
        $modalUrl = route('forms.modal', $form->slug);

        $response
            ->assertSee('data-form-action-modal-url="'.$modalUrl.'"', false)
            ->assertDontSee('_context_page_id=', false)
            ->assertDontSee('_context_block_id=', false)
            ->assertDontSee('name="email"', false);

        $this->post($modalUrl, [
            '_context_page_id' => $page->getKey(),
            '_context_page_url' => '/'.$page->slug,
            '_context_block_id' => self::ID,
        ])
            ->assertOk()
            ->assertSee('name="email"', false)
            ->assertDontSee('name="_context_page_id"', false)
            ->assertDontSee('name="_context_block_id"', false)
            ->assertSee('name="_display_mode" value="modal"', false);

        $this->assertSame(
            $page->getKey(),
            session("forms.attribution.{$form->getKey()}.page_id"),
        );
    }

    public function test_editor_persists_a_form_action_with_stable_identity(): void
    {
        $form = $this->form('modal');
        $this->actingAs(User::factory()->create());
        $page = Page::factory()->create(['blocks' => [['type' => 'cta', 'data' => $this->legacyCta('classic')]]]);
        $component = Livewire::test(EditPage::class, ['record' => $page->getRouteKey()]);
        $uuid = array_key_first($component->get('data')['blocks']);

        $component
            ->set("data.blocks.{$uuid}.data.content.primary_cta.action.type", 'form')
            ->set("data.blocks.{$uuid}.data.content.primary_cta.action.reference_id", $form->getKey())
            ->set("data.blocks.{$uuid}.data.content.primary_cta.action.display", 'modal')
            ->call('save')
            ->assertHasNoFormErrors();

        $saved = $page->fresh()->blocks[0]['data'];
        $this->assertSame(self::ID, $saved['block_id']);
        $this->assertSame(
            [
                'schema_version' => 1,
                'type' => 'form',
                'reference_id' => $form->getKey(),
                'display' => 'modal',
                'open_in_new_tab' => false,
            ],
            $saved['content']['primary_cta']['action'],
        );
    }

    public function test_page_display_action_overrides_a_modal_form_default(): void
    {
        $form = $this->form('modal');
        $html = $this->render([
            'block_id' => self::ID,
            'schema_version' => 2,
            'template' => 'classic',
            'content' => [
                'title' => 'CTA title',
                'primary_cta' => [
                    'label' => 'Open page',
                    'action' => [
                        'type' => 'form',
                        'form_id' => $form->getKey(),
                        'display' => 'page',
                    ],
                ],
            ],
        ]);

        $this->assertStringContainsString(route('forms.context', $form->slug), $html);
        $this->assertStringNotContainsString('data-form-action-modal-url', $html);
    }

    public function test_modal_form_submission_preserves_cta_attribution(): void
    {
        $form = $this->form('modal');
        $page = Page::factory()->published()->create();

        $this->post(route('forms.modal', $form->slug), [
            '_context_page_id' => $page->getKey(),
            '_context_page_url' => '/from-modal',
            '_context_block_id' => self::ID,
        ])->assertOk();

        $this->post(route('forms.submit', $form->slug), [
            'email' => 'modal@example.com',
            '_display_mode' => 'modal',
        ])->assertRedirect(route('forms.show', $form->slug));

        $submission = FormSubmission::query()->sole();
        $lead = Lead::query()->sole();
        $this->assertSame($page->getKey(), $submission->page_id);
        $this->assertSame('/from-modal', $submission->page_url);
        $this->assertSame(self::ID, $submission->block_id);
        $this->assertSame(self::ID, $lead->block_id);
        $this->assertSame('modal@example.com', $lead->email);
    }

    private function legacyCta(string $template): array
    {
        return [
            'block_id' => self::ID, 'cta_template' => $template, 'eyebrow' => 'Eyebrow',
            'title' => 'CTA title', 'description' => 'CTA description', 'heading_tag' => 'h2',
            'button_label' => 'Primary', 'button_url' => '/primary',
            'secondary_button_label' => 'Secondary', 'secondary_button_url' => '/secondary',
            'background_image' => '/background.jpg', 'content_width' => 620,
            'section_background' => 'muted', 'alignment' => 'center',
            'background_image_width_value' => 80, 'background_image_width_unit' => '%',
            'background_image_mobile_fit' => 'cover',
        ];
    }

    private function render(array $data, array $context = []): string
    {
        return view('partials.blocks.cta', compact('data', 'context'))->render();
    }

    private function form(string $displayMode): Form
    {
        return Form::query()->create([
            'name' => 'CTA Form',
            'slug' => 'cta-form-'.$displayMode,
            'status' => 'published',
            'display_mode' => $displayMode,
            'schema_version' => 1,
            'schema' => ['fields' => [
                ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
            ]],
        ]);
    }
}
