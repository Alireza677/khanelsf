<?php

namespace Tests\Feature;

use App\CMS\Actions\Filament\ActionPicker;
use App\Filament\Resources\PageResource\Pages\EditPage;
use App\Filament\Resources\TemplateResource\Pages\EditTemplate;
use App\Models\Form;
use App\Models\Page;
use App\Models\Template;
use App\Models\User;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TestimonialsSliderTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_and_template_editors_keep_legacy_items_and_expose_rich_quote_and_canonical_cta(): void
    {
        $this->actingAs(User::factory()->create());
        $page = Page::factory()->create(['blocks' => [$this->block()]]);
        $template = Template::query()->create([
            'title' => 'Testimonials template',
            'slug' => 'testimonials-template',
            'type' => 'page',
            'status' => 'draft',
            'blocks' => [$this->block()],
        ]);

        foreach ([[EditPage::class, $page], [EditTemplate::class, $template]] as [$componentClass, $record]) {
            $component = Livewire::test($componentClass, ['record' => $record->getRouteKey()]);
            $builder = collect($component->instance()->form->getFlatComponents(withHidden: true))
                ->first(fn ($field): bool => $field instanceof Builder && $field->getName() === 'blocks');
            $testimonial = $builder->getBlock('testimonials');
            $fields = collect($testimonial->getChildComponents());
            $items = $fields->first(fn ($field): bool => $field instanceof Repeater && $field->getStatePath(false) === 'items');

            $this->assertInstanceOf(RichEditor::class, collect($items->getChildComponents())->first(
                fn ($field): bool => $field instanceof RichEditor && $field->getStatePath(false) === 'quote',
            ));
            $this->assertInstanceOf(ActionPicker::class, $fields->first(
                fn ($field): bool => $field instanceof ActionPicker && $field->getStatePath(false) === 'cta_action',
            ));
            $this->assertTrue($items->isCollapsible());
            $itemLabelProperty = new \ReflectionProperty(Repeater::class, 'itemLabel');
            $itemLabel = $itemLabelProperty->getValue($items);
            $this->assertInstanceOf(\Closure::class, $itemLabel);
            $this->assertSame('First', $itemLabel(['name' => 'First']));
            $this->assertSame('نظر جدید', $itemLabel(['name' => '']));

            $blockKey = array_key_first($component->get('data.blocks'));
            $itemKey = array_key_first($component->get("data.blocks.{$blockKey}.data.items"));
            $component->assertSet("data.blocks.{$blockKey}.data.items.{$itemKey}.quote", 'Legacy first')->call('save')->assertHasNoFormErrors();
            $this->assertSame('Legacy first', $record->fresh()->blocks[0]['data']['items'][0]['quote']);
        }
    }

    public function test_multiple_items_render_first_active_navigation_indicators_and_accessibility_contract(): void
    {
        $html = $this->render($this->block()['data']);

        $this->assertSame(3, preg_match_all('/\sdata-testimonial-slide(?:\s|>)/', $html));
        $this->assertSame(3, substr_count($html, 'data-testimonial-indicator='));
        $this->assertStringContainsString('testimonial-slider__slide is-active', $html);
        $this->assertStringContainsString('aria-current="true"', $html);
        $this->assertStringContainsString('data-testimonial-previous', $html);
        $this->assertStringContainsString('data-testimonial-next', $html);
        $this->assertStringContainsString('aria-label="نظر قبلی"', $html);
        $this->assertStringContainsString('aria-label="نظر بعدی"', $html);
        $this->assertStringContainsString('data-testimonial-previous><i class="icon-arrow-left-2"', $html);
        $this->assertStringContainsString('data-testimonial-next><i class="icon-arrow-right-3"', $html);
        $this->assertStringContainsString('tabindex="0"', $html);
        $this->assertStringContainsString('activeIndex = (requestedIndex + slides.length) % slides.length', $html);
        $this->assertStringContainsString("event.key === 'ArrowLeft'", $html);
        $this->assertStringContainsString("event.key === 'ArrowRight'", $html);
        $this->assertStringContainsString('delta < 0 ? next() : previous()', $html);
        $this->assertStringContainsString('show(Number(indicator.dataset.testimonialIndicator))', $html);
        $css = file_get_contents(resource_path('css/app.css'));
        $this->assertStringContainsString(".testimonial-slider__stage {\n    align-items: center;\n    direction: ltr;", $css);
        $this->assertStringContainsString('max-width: 700px;', $css);
    }

    public function test_single_item_hides_slider_controls_and_missing_cta_has_no_empty_wrapper(): void
    {
        $html = $this->render([
            'section_title' => 'One',
            'items' => [['quote' => 'Only quote', 'name' => 'Only customer']],
        ]);

        $this->assertStringContainsString('Only quote', $html);
        $this->assertStringNotContainsString('aria-label="نظر قبلی" data-testimonial-previous', $html);
        $this->assertStringNotContainsString('aria-label="نظر بعدی" data-testimonial-next', $html);
        $this->assertStringNotContainsString('testimonial-slider__indicators', $html);
        $this->assertStringNotContainsString('testimonial-slider__cta', $html);
    }

    public function test_rich_quote_is_sanitized_and_valid_optional_cta_uses_action_presentation(): void
    {
        $data = $this->block()['data'];
        $data['items'][0]['quote'] = '<p>Fast <strong>delivery</strong></p><script>alert(1)</script>';
        $data['cta_label'] = 'View projects';
        $data['cta_action'] = ['type' => 'custom_url', 'value' => '/projects'];
        $html = $this->render($data);

        $this->assertStringContainsString('<p>Fast <strong>delivery</strong></p>', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('testimonial-slider__cta', $html);
        $this->assertStringContainsString('href="/projects"', $html);
        $this->assertStringContainsString('>View projects</a>', $html);

        $data['cta_action'] = ['type' => 'custom_url', 'value' => 'javascript:alert(1)'];
        $invalidHtml = $this->render($data);
        $this->assertStringNotContainsString('testimonial-slider__cta', $invalidHtml);
        $this->assertStringContainsString('Fast', $invalidHtml);
    }

    public function test_form_cta_and_production_page_render_without_changing_legacy_shape(): void
    {
        $form = Form::query()->create([
            'name' => 'Testimonials form',
            'slug' => 'testimonials-form',
            'status' => 'published',
            'display_mode' => 'modal',
        ]);
        $block = $this->block();
        $block['data']['cta_label'] = 'Contact us';
        $block['data']['cta_action'] = ['type' => 'form', 'reference_id' => $form->getKey(), 'display' => 'modal'];
        $page = Page::factory()->published()->create(['slug' => 'testimonials-page', 'blocks' => [$block]]);

        $response = $this->get(route('pages.show', $page->slug))->assertOk();
        $response->assertSee('data-testimonial-slider', false)
            ->assertSee('data-form-action-modal-url', false)
            ->assertSee('Contact us');
        $this->assertSame(['name', 'role', 'quote'], array_keys($page->fresh()->blocks[0]['data']['items'][0]));
    }

    private function block(): array
    {
        return ['type' => 'testimonials', 'data' => [
            'section_title' => 'Customer voices',
            'items' => [
                ['name' => 'First', 'role' => 'Tehran', 'quote' => 'Legacy first'],
                ['name' => 'Second', 'role' => 'Shiraz', 'quote' => 'Legacy second'],
                ['name' => 'Third', 'role' => null, 'quote' => 'Legacy third'],
            ],
        ]];
    }

    private function render(array $data): string
    {
        return view('partials.blocks.testimonials', [
            'data' => $data,
            'context' => ['page_url' => '/testimonials'],
        ])->render();
    }
}
