<?php

namespace Tests\Feature;

use App\Filament\Resources\FormResource\Pages\CreateForm;
use App\Filament\Resources\FormResource\Pages\EditForm;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Page;
use App\Models\User;
use App\Services\SubmissionAnswerSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FormDateFieldTest extends TestCase
{
    use RefreshDatabase;

    public function test_builder_adds_and_persists_the_canonical_date_field_settings(): void
    {
        $this->actingAs(User::factory()->create());

        $created = Livewire::test(CreateForm::class)
            ->assertSee('انتخاب تاریخ')
            ->callFormComponentAction('schema.fields', 'add', arguments: ['fieldType' => 'date'])
            ->assertHasNoFormComponentActionErrors();

        $fields = $created->get('data')['schema']['fields'];
        $this->assertSame('date', $fields[array_key_last($fields)]['type']);
        $this->assertFalse($fields[array_key_last($fields)]['date_range_enabled']);

        $form = $this->form();
        $component = Livewire::test(EditForm::class, ['record' => $form->getRouteKey()]);
        $component
            ->assertSee('انتخاب تاریخ')
            ->assertSee('حداقل تاریخ')
            ->assertSee('حداکثر تاریخ');
        $fieldKey = array_key_first($component->get('data')['schema']['fields']);

        $component
            ->set("data.schema.fields.{$fieldKey}.label", 'تاریخ مراجعه')
            ->set("data.schema.fields.{$fieldKey}.placeholder", 'تاریخ را انتخاب کنید')
            ->set("data.schema.fields.{$fieldKey}.required", true)
            ->set("data.schema.fields.{$fieldKey}.layout.span", 6)
            ->set("data.schema.fields.{$fieldKey}.min_date", '2026-08-20')
            ->set("data.schema.fields.{$fieldKey}.max_date", '2026-08-30')
            ->call('save')
            ->assertHasNoFormErrors();

        $saved = $form->fresh()->schema['fields'][0];
        $this->assertSame('date', $saved['type']);
        $this->assertSame(6, $saved['layout']['span']);
        $this->assertSame('2026-08-20', $saved['min_date']);
        $this->assertSame('2026-08-30', $saved['max_date']);

        Livewire::test(EditForm::class, ['record' => $form->getRouteKey()])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('2026-08-30', $form->fresh()->schema['fields'][0]['max_date']);
    }

    public function test_date_type_can_change_to_text_and_back_without_invalid_type_state(): void
    {
        $this->actingAs(User::factory()->create());
        $form = $this->form();
        $component = Livewire::test(EditForm::class, ['record' => $form->getRouteKey()]);
        $fieldKey = array_key_first($component->get('data')['schema']['fields']);

        $component
            ->set("data.schema.fields.{$fieldKey}.type", 'text')
            ->call('save')
            ->assertHasNoFormErrors();
        $savedAsText = $form->fresh()->schema['fields'][0];
        $this->assertSame('text', $savedAsText['type']);
        $this->assertSame('2026-08-20', $savedAsText['min_date']);
        $this->assertSame('2026-08-30', $savedAsText['max_date']);

        $component
            ->set("data.schema.fields.{$fieldKey}.type", 'date')
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('date', $form->fresh()->schema['fields'][0]['type']);
    }

    public function test_date_specific_settings_are_hidden_for_a_text_field(): void
    {
        $this->actingAs(User::factory()->create());
        $field = $this->dateField('plain_text', 'فیلد متنی');
        $field['type'] = 'text';

        Livewire::test(EditForm::class, ['record' => $this->form([$field])->getRouteKey()])
            ->assertOk()
            ->assertDontSee('حداقل تاریخ')
            ->assertDontSee('حداکثر تاریخ');
    }

    public function test_range_toggle_controls_runtime_boundaries_without_deleting_values(): void
    {
        $this->actingAs(User::factory()->create());
        $field = $this->dateField('bounded_date', 'تاریخ محدود');
        $field['date_range_enabled'] = false;
        $form = $this->form([$field]);

        $html = $this->get(route('forms.show', $form->slug))->assertOk()->getContent();
        $this->assertStringNotContainsString('data-min-date=', $html);
        $this->assertStringNotContainsString('data-max-date=', $html);

        $this->post(route('forms.submit', $form->slug), ['bounded_date' => '2026-08-19'])
            ->assertSessionHasNoErrors();

        $component = Livewire::test(EditForm::class, ['record' => $form->getRouteKey()]);
        $fieldKey = array_key_first($component->get('data')['schema']['fields']);
        $component
            ->assertDontSee('حداقل تاریخ')
            ->assertDontSee('حداکثر تاریخ')
            ->set("data.schema.fields.{$fieldKey}.date_range_enabled", true)
            ->assertSee('حداقل تاریخ')
            ->assertSee('حداکثر تاریخ')
            ->call('save')
            ->assertHasNoFormErrors();

        $saved = $form->fresh()->schema['fields'][0];
        $this->assertTrue($saved['date_range_enabled']);
        $this->assertSame('2026-08-20', $saved['min_date']);
        $this->assertSame('2026-08-30', $saved['max_date']);
    }

    public function test_builder_rejects_an_inverted_enabled_date_range(): void
    {
        $this->actingAs(User::factory()->create());
        $form = $this->form();
        $component = Livewire::test(EditForm::class, ['record' => $form->getRouteKey()]);
        $fieldKey = array_key_first($component->get('data')['schema']['fields']);

        $component
            ->set("data.schema.fields.{$fieldKey}.date_range_enabled", true)
            ->set("data.schema.fields.{$fieldKey}.min_date", '2026-08-30')
            ->set("data.schema.fields.{$fieldKey}.max_date", '2026-08-20')
            ->call('save')
            ->assertHasFormErrors(["schema.fields.{$fieldKey}.max_date"]);
    }

    public function test_frontend_renders_independent_jalali_picker_contracts_and_grid_span(): void
    {
        $form = $this->form([
            $this->dateField('start_date', 'تاریخ شروع', 6),
            $this->dateField('end_date', 'تاریخ پایان', 6),
            $this->dateField('birthday', 'تاریخ تولد', 12),
        ]);

        $html = $this->get(route('forms.show', $form->slug))->assertOk()->getContent();

        $this->assertSame(3, substr_count($html, 'data-form-date-picker'));
        $this->assertSame(3, substr_count($html, 'data-form-date-canonical'));
        $this->assertSame(2, substr_count($html, 'form-field--span-6'));
        $this->assertStringContainsString('data-min-date="2026-08-20"', $html);
        $this->assertStringContainsString('data-max-date="2026-08-30"', $html);
        $this->assertStringNotContainsString('type="date"', $html);

        preg_match_all('/id="([^"]+)"[^>]+data-form-date-canonical/', $html, $ids);
        $this->assertCount(3, array_unique($ids[1]));
    }

    public function test_server_rejects_invalid_and_out_of_range_dates_and_stores_iso_date(): void
    {
        $form = $this->form([$this->dateField('visit_date', 'تاریخ مراجعه', 12, true)]);

        $this->post(route('forms.submit', $form->slug), ['visit_date' => '۱۴۰۵/۰۶/۰۳'])
            ->assertSessionHasErrors('visit_date');
        $this->post(route('forms.submit', $form->slug), ['visit_date' => '2026-08-19'])
            ->assertSessionHasErrors('visit_date');
        $this->post(route('forms.submit', $form->slug), ['visit_date' => '2026-08-31'])
            ->assertSessionHasErrors('visit_date');

        $this->post(route('forms.submit', $form->slug), ['visit_date' => '2026-08-25'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $submission = FormSubmission::query()->sole();
        $this->assertSame('2026-08-25', $submission->payload['visit_date']);
        $snapshot = $submission->payload[SubmissionAnswerSnapshot::PAYLOAD_KEY][0];
        $this->assertSame('2026-08-25', $snapshot['raw_value']);
        $this->assertSame('۱۴۰۵/۰۶/۰۳', $snapshot['display_value']);
    }

    public function test_server_enforces_the_global_jalali_1350_floor_without_a_business_range(): void
    {
        $field = $this->dateField('historical_date', 'تاریخ قدیمی');
        $field['date_range_enabled'] = false;
        $form = $this->form([$field]);

        $this->post(route('forms.submit', $form->slug), ['historical_date' => '1971-03-20'])
            ->assertSessionHasErrors('historical_date');
        $this->post(route('forms.submit', $form->slug), ['historical_date' => '1971-03-21'])
            ->assertSessionHasNoErrors();
    }

    public function test_optional_date_can_be_cleared(): void
    {
        $form = $this->form([$this->dateField('optional_date', 'تاریخ اختیاری')]);

        $this->post(route('forms.submit', $form->slug), ['optional_date' => ''])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertNull(FormSubmission::query()->sole()->payload['optional_date']);
    }

    public function test_public_picker_assets_are_idempotent_accessible_and_mobile_safe(): void
    {
        $javascript = file_get_contents(resource_path('js/components/form-date-pickers.js'));
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString('datePickerInitialized', $javascript);
        $this->assertStringContainsString("event.key === 'Escape'", $javascript);
        $this->assertStringContainsString('activePicker', $javascript);
        $this->assertStringContainsString('event.composedPath().includes(root)', $javascript);
        $this->assertStringContainsString("monthSelect.setAttribute('aria-label', 'انتخاب ماه')", $javascript);
        $this->assertStringContainsString("yearSelect.setAttribute('aria-label', 'انتخاب سال')", $javascript);
        $this->assertStringContainsString('const globalFloor = { year: 1350, month: 1, day: 1 };', $javascript);
        $this->assertStringContainsString("window.addEventListener('resize'", $javascript);
        $this->assertStringContainsString('max-width: min(20rem, calc(100vw - 2rem));', $css);
        $this->assertStringContainsString('min-height: 2.5rem;', $css);
    }

    public function test_two_forms_on_one_page_receive_isolated_date_field_ids(): void
    {
        $first = $this->form([$this->dateField('first_date', 'تاریخ فرم اول')]);
        $second = $this->form([$this->dateField('second_date', 'تاریخ فرم دوم')]);
        $page = Page::factory()->published()->create([
            'slug' => 'two-date-forms',
            'blocks' => [
                $this->formBlock($first, '01ARZ3NDEKTSV4RRFFQ69G5FAV'),
                $this->formBlock($second, '01ARZ3NDEKTSV4RRFFQ69G5FAW'),
            ],
        ]);

        $html = $this->get(route('pages.show', $page->slug))->assertOk()->getContent();
        preg_match_all('/id="([^"]+)"[^>]+data-form-date-canonical/', $html, $ids);

        $this->assertCount(2, $ids[1]);
        $this->assertCount(2, array_unique($ids[1]));
    }

    private function form(?array $fields = null): Form
    {
        return Form::query()->create([
            'name' => 'Date Form',
            'slug' => 'date-form-'.Form::query()->count(),
            'status' => 'published',
            'display_mode' => 'page',
            'type' => 'normal',
            'schema_version' => 2,
            'schema' => ['fields' => $fields ?? [$this->dateField('visit_date', 'تاریخ مراجعه')]],
            'settings' => [],
        ]);
    }

    private function dateField(string $key, string $label, int $span = 12, bool $required = false): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'type' => 'date',
            'placeholder' => 'تاریخ را انتخاب کنید',
            'required' => $required,
            'min_date' => '2026-08-20',
            'max_date' => '2026-08-30',
            'layout' => ['span' => $span],
        ];
    }

    private function formBlock(Form $form, string $blockId): array
    {
        return [
            'type' => 'form',
            'data' => [
                'block_id' => $blockId,
                'schema_version' => 1,
                'template' => 'split',
                'content' => ['form_id' => $form->getKey()],
            ],
        ];
    }
}
