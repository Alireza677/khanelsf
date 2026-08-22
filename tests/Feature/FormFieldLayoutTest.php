<?php

namespace Tests\Feature;

use App\Filament\Resources\FormResource\Pages\EditForm;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Page;
use App\Models\User;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FormFieldLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_builder_exposes_preset_width_control_and_save_reload_preserves_spans(): void
    {
        $this->actingAs(User::factory()->create());
        $form = $this->form([6, 6]);
        $component = Livewire::test(EditForm::class, ['record' => $form->getRouteKey()]);
        $fieldKeys = array_keys($component->get('data')['schema']['fields']);
        $flat = collect($component->instance()->form->getFlatComponents(withHidden: true));
        $span = $flat->first(fn (Component $field): bool => $field instanceof Select && $field->getName() === 'layout.span');

        $this->assertInstanceOf(Select::class, $span);
        $this->assertSame([12, 9, 8, 6, 4, 3], array_map('intval', array_keys($span->getOptions())));

        $component
            ->set("data.schema.fields.{$fieldKeys[0]}.layout.span", 4)
            ->set("data.schema.fields.{$fieldKeys[1]}.layout.span", 3)
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame([4, 3], array_column(array_column($form->fresh()->schema['fields'], 'layout'), 'span'));

        $reloaded = Livewire::test(EditForm::class, ['record' => $form->getRouteKey()])->get('data');
        $this->assertSame([4, 3], array_column(array_column($reloaded['schema']['fields'], 'layout'), 'span'));
    }

    public function test_page_marker_is_forced_full_width_and_legacy_hydration_does_not_write_record(): void
    {
        $this->actingAs(User::factory()->create());
        $form = $this->form([null], [['key' => 'step_one', 'label' => 'مرحله', 'type' => 'page', 'layout' => ['span' => 3]]]);
        $before = $form->schema;
        $data = Livewire::test(EditForm::class, ['record' => $form->getRouteKey()])->get('data');

        $this->assertSame(12, data_get(collect(data_get($data, 'schema.fields'))->first(), 'layout.span'));
        $this->assertSame($before, $form->fresh()->schema);
    }

    public function test_canonical_renderer_outputs_grid_spans_in_dom_order_for_all_form_contexts(): void
    {
        $form = $this->form([6, 6, 4, 4, 4, 3, 3, 3, 3, 8, 4, 9, 3, 12]);
        $page = Page::factory()->published()->create([
            'slug' => 'form-layout-page',
            'blocks' => [[
                'type' => 'form',
                'data' => [
                    'block_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
                    'schema_version' => 1,
                    'template' => 'split',
                    'content' => ['form_id' => $form->getKey()],
                ],
            ]],
        ]);

        $standalone = $this->get(route('forms.show', $form->slug))->assertOk()->getContent();
        $embedded = $this->get(route('pages.show', $page->slug))->assertOk()->getContent();
        $modal = $this->post(route('forms.modal', $form->slug))->assertOk()->getContent();

        foreach ([$standalone, $embedded, $modal] as $html) {
            $this->assertStringContainsString('class="form-step form-fields"', $html);
            foreach ([12, 9, 8, 6, 4, 3] as $span) {
                $this->assertStringContainsString("form-field--span-{$span}", $html);
            }
            $this->assertLessThan(strpos($html, 'name="field_2"'), strpos($html, 'name="field_1"'));
        }
    }

    public function test_legacy_and_invalid_spans_render_full_width_and_layout_never_enters_submission_payload(): void
    {
        $form = $this->form([null, 5]);

        $html = $this->get(route('forms.show', $form->slug))->assertOk()->getContent();
        $this->assertSame(2, substr_count($html, 'form-field--span-12'));

        $this->post(route('forms.submit', $form->slug), [
            'field_1' => 'A',
            'field_2' => 'B',
            'layout' => ['span' => 3],
        ])->assertRedirect();

        $payload = FormSubmission::query()->sole()->payload;
        $this->assertSame('A', $payload['field_1']);
        $this->assertSame('B', $payload['field_2']);
        $this->assertArrayNotHasKey('layout', $payload);
    }

    public function test_grid_css_uses_twelve_columns_and_mobile_full_width_without_manual_percentages(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString('grid-template-columns: repeat(12, minmax(0, 1fr));', $css);
        $this->assertStringContainsString('.form-field--span-6 { grid-column: span 6; }', $css);
        $this->assertStringContainsString('.form-field[class*="form-field--span-"]', $css);
        $this->assertStringNotContainsString('.form-field--span-6 { width:', $css);
    }

    private function form(array $spans, ?array $fields = null): Form
    {
        $fields ??= collect($spans)->map(fn (?int $span, int $index): array => [
            'field_id' => str_pad((string) ($index + 1), 26, '0', STR_PAD_LEFT),
            'key' => 'field_'.($index + 1),
            'label' => 'Field '.($index + 1),
            'type' => $index === 9 ? 'textarea' : 'text',
            'required' => false,
            ...($span === null ? [] : ['layout' => ['span' => $span]]),
        ])->all();

        return Form::query()->create([
            'name' => 'Layout Form',
            'slug' => 'layout-form-'.Form::query()->count(),
            'status' => 'published',
            'display_mode' => 'page',
            'type' => 'normal',
            'schema_version' => 2,
            'schema' => ['fields' => $fields],
            'settings' => [],
        ]);
    }
}
