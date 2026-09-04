<?php

namespace Tests\Feature;

use App\Filament\Resources\FormResource;
use App\Filament\Resources\FormResource\Pages\CreateForm;
use App\Filament\Resources\FormResource\Pages\EditForm;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\User;
use App\Services\FormSchema;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FormBuilderEditorTest extends TestCase
{
    use RefreshDatabase;

    public function test_palette_exposes_radio_but_keeps_legacy_radio_card_editable_only(): void
    {
        $this->actingAs(User::factory()->create());
        $component = Livewire::test(CreateForm::class);
        $settingsType = collect($component->instance()->form->getFlatComponents(withHidden: true))
            ->filter(fn ($field): bool => $field instanceof Select && $field->getName() === 'type')
            ->first(fn (Select $field): bool => array_key_exists('date', $field->getOptions()));

        $this->assertInstanceOf(Select::class, $settingsType);
        $this->assertSame('انتخاب تاریخ', $settingsType->getOptions()['date']);

        $paletteTypes = array_keys(FormResource::fieldTypeLabels());
        $settingsTypes = array_keys($settingsType->getOptions());
        $schemaTypes = FormSchema::supportedTypes();
        sort($paletteTypes);
        sort($settingsTypes);
        sort($schemaTypes);

        $this->assertContains('radio', $paletteTypes);
        $this->assertNotContains('radio_card', $paletteTypes);
        $this->assertContains('radio_card', $settingsTypes);
        $this->assertSame($settingsTypes, $schemaTypes);
    }

    public function test_editor_renders_two_panel_canvas_palette_and_compact_structural_items(): void
    {
        $this->actingAs(User::factory()->create());
        $form = $this->form();

        Livewire::test(EditForm::class, ['record' => $form->getRouteKey()])
            ->assertOk()
            ->assertSee('افزودن فیلد')
            ->assertSee('تنظیمات فیلد')
            ->assertSee('جستجوی فیلد')
            ->assertSee('فیلدهای استاندارد')
            ->assertSee('فیلدهای ساختاری')
            ->assertSee('فیلدهای انتخابی')
            ->assertSee('فیلدهای پیشرفته')
            ->assertSee('مرحله دوم: مشخصات اجرایی')
            ->assertSeeHtml('class="form-builder-inspector"')
            ->assertSeeHtml('class="form-builder-canvas"')
            ->assertSeeHtml('is-structural')
            ->assertDontSeeHtml('form-builder-card__preview')
            ->assertDontSee('مدیریت گزینه‌ها')
            ->assertDontSee('امتیازها');
    }

    public function test_field_cards_keep_builder_controls_without_rendering_visual_field_previews(): void
    {
        $this->actingAs(User::factory()->create());
        $form = $this->choiceForm();

        Livewire::test(EditForm::class, ['record' => $form->getRouteKey()])
            ->assertOk()
            ->assertSeeHtml('x-sortable-item=')
            ->assertSeeHtml('x-sortable-handle')
            ->assertSeeHtml('form-builder-card__actions')
            ->assertSeeHtml('form-builder-card__meta')
            ->assertSeeHtml('is-required')
            ->assertSee('۱۰۰٪')
            ->assertDontSeeHtml('form-builder-card__preview');
    }

    public function test_palette_add_uses_the_existing_repeater_state_and_action(): void
    {
        $this->actingAs(User::factory()->create());

        $component = Livewire::test(CreateForm::class)
            ->callFormComponentAction('schema.fields', 'add', arguments: ['fieldType' => 'email'])
            ->assertHasNoFormComponentActionErrors();

        $fields = array_values($component->get('data')['schema']['fields']);

        $this->assertSame('email', $fields[array_key_last($fields)]['type']);
        $this->assertSame('ایمیل', $fields[array_key_last($fields)]['label']);
    }

    public function test_palette_can_create_radio_but_rejects_new_legacy_radio_cards(): void
    {
        $this->actingAs(User::factory()->create());

        $component = Livewire::test(CreateForm::class)
            ->assertSee('رادیویی')
            ->callFormComponentAction('schema.fields', 'add', arguments: ['fieldType' => 'radio']);

        $fields = array_values($component->get('data')['schema']['fields']);
        $this->assertSame('radio', $fields[array_key_last($fields)]['type']);

        $component->callFormComponentAction(
            'schema.fields',
            'add',
            arguments: ['fieldType' => 'radio_card'],
        );
        $fields = array_values($component->get('data')['schema']['fields']);
        $this->assertSame('text', $fields[array_key_last($fields)]['type']);
    }

    public function test_scoring_settings_only_render_for_calculator_rich_choice_fields(): void
    {
        $this->actingAs(User::factory()->create());
        $form = $this->form([
            'type' => 'calculator',
            'calculator_identifier' => 'editor_test_v1',
            'schema' => [
                'fields' => [[
                    'name' => 'method',
                    'label' => 'روش اجرا',
                    'type' => 'radio_card',
                    'required' => true,
                    'options' => [[
                        'value' => 'prefab',
                        'label' => 'پیش ساخته',
                        'scores' => ['prefab' => 3],
                    ]],
                ]],
                'calculator' => ['recommendations' => ['prefab' => 'پیش ساخته']],
            ],
        ]);

        Livewire::test(EditForm::class, ['record' => $form->getRouteKey()])
            ->assertOk()
            ->assertSee('نتایج محاسبه')
            ->assertSee('نتایج پیشنهادی')
            ->assertSee('امتیازدهی نتایج')
            ->assertSee('عنوان نتیجه')
            ->assertDontSee('کلید پیشنهاد');
    }

    public function test_calculator_score_editor_persists_standard_radio_and_checkbox_scores(): void
    {
        $this->actingAs(User::factory()->create());
        $form = $this->form([
            'type' => 'calculator',
            'calculator_identifier' => 'standard_choice_scores_v1',
            'schema' => [
                'fields' => [
                    [
                        'key' => 'priority',
                        'label' => 'اولویت',
                        'type' => 'radio',
                        'required' => true,
                        'options' => [[
                            'value' => 'speed',
                            'label' => 'سرعت',
                            'scores' => ['lsf' => 3, 'steel' => 1],
                        ]],
                    ],
                    [
                        'key' => 'features',
                        'label' => 'ویژگی‌ها',
                        'type' => 'checkbox',
                        'required' => false,
                        'options' => [[
                            'value' => 'thermal',
                            'label' => 'عایق',
                            'scores' => ['lsf' => 2, 'steel' => 1],
                        ]],
                    ],
                ],
                'calculator' => ['recommendations' => [
                    'lsf' => 'LSF',
                    'steel' => 'سازه فولادی',
                ]],
            ],
        ]);
        $component = Livewire::test(EditForm::class, ['record' => $form->getRouteKey()])
            ->assertSee('امتیازدهی نتایج');
        $fields = $component->get('data')['schema']['fields'];

        foreach (array_keys($fields) as $fieldItem) {
            if (! in_array($fields[$fieldItem]['type'], ['radio', 'checkbox'], true)) {
                continue;
            }

            $optionItem = array_key_first($fields[$fieldItem]['options']);
            $scoreItem = array_key_first($fields[$fieldItem]['options'][$optionItem]['scores']);
            $component->set(
                "data.schema.fields.{$fieldItem}.options.{$optionItem}.scores.{$scoreItem}.score",
                $fields[$fieldItem]['type'] === 'radio' ? 8 : 6,
            );
        }

        $component->call('save')->assertHasNoFormErrors();
        $savedFields = $form->fresh()->schema['fields'];

        $this->assertSame(8, $savedFields[0]['options'][0]['scores']['lsf']);
        $this->assertSame(6, $savedFields[1]['options'][0]['scores']['lsf']);
    }

    public function test_scoring_actions_are_non_submit_and_preserve_unsaved_builder_state(): void
    {
        $this->actingAs(User::factory()->create());
        $form = $this->reactiveScoringForm();
        $storedSchema = $form->schema;
        $component = Livewire::test(EditForm::class, ['record' => $form->getRouteKey()])
            ->assertSee('افزودن امتیاز')
            ->assertSeeHtml('wire:key="form-builder-choice-metadata-')
            ->assertSeeHtml('x-bind:open="metadataOpen[');
        $scoreRepeater = collect($component->instance()->form->getFlatComponents(withHidden: true))
            ->first(fn ($field): bool => $field instanceof Repeater
                && str_ends_with($field->getStatePath(), '.scores'));

        $this->assertInstanceOf(Repeater::class, $scoreRepeater);
        $addAction = $scoreRepeater->getAction($scoreRepeater->getAddActionName());
        $deleteAction = $scoreRepeater->getAction($scoreRepeater->getDeleteActionName());
        $reorderAction = $scoreRepeater->getAction($scoreRepeater->getReorderActionName());
        $this->assertFalse($addAction->canSubmitForm());
        $this->assertFalse($deleteAction->canSubmitForm());
        $this->assertFalse($reorderAction->canSubmitForm());
        $this->assertStringContainsString(
            'mountFormComponentAction',
            $addAction->getLivewireClickHandler(),
        );

        $fields = $component->get('data')['schema']['fields'];
        $fieldKey = array_key_first($fields);
        $optionKey = array_key_first($fields[$fieldKey]['options']);
        $scorePath = "schema.fields.{$fieldKey}.options.{$optionKey}.scores";
        $initialCount = count(data_get($component->get('data'), $scorePath, []));

        $component
            ->set("data.schema.fields.{$fieldKey}.label", 'عنوان ذخیره‌نشده')
            ->set("data.schema.fields.{$fieldKey}.required", false)
            ->set("data.schema.fields.{$fieldKey}.layout.span", 6)
            ->set("data.schema.fields.{$fieldKey}.options.{$optionKey}.label", 'گزینه ویرایش‌شده');

        foreach (range(1, 3) as $addition) {
            $component
                ->callFormComponentAction($scorePath, 'add')
                ->assertHasNoFormComponentActionErrors();
            $this->assertCount(
                $initialCount + $addition,
                data_get($component->get('data'), $scorePath),
            );
        }

        $this->assertSame('عنوان ذخیره‌نشده', data_get($component->get('data'), "schema.fields.{$fieldKey}.label"));
        $this->assertFalse(data_get($component->get('data'), "schema.fields.{$fieldKey}.required"));
        $this->assertSame(6, data_get($component->get('data'), "schema.fields.{$fieldKey}.layout.span"));
        $this->assertSame('گزینه ویرایش‌شده', data_get($component->get('data'), "schema.fields.{$fieldKey}.options.{$optionKey}.label"));
        $this->assertSame($storedSchema, $form->fresh()->schema);

        $scoreKeys = array_keys(data_get($component->get('data'), $scorePath));
        $deletedKey = array_pop($scoreKeys);
        $component
            ->callFormComponentAction($scorePath, 'delete', arguments: ['item' => $deletedKey])
            ->assertHasNoFormComponentActionErrors();
        $this->assertCount($initialCount + 2, data_get($component->get('data'), $scorePath));
        $this->assertSame($storedSchema, $form->fresh()->schema);

        $reversedKeys = array_reverse(array_keys(data_get($component->get('data'), $scorePath)));
        $component
            ->callFormComponentAction($scorePath, 'reorder', arguments: ['items' => $reversedKeys])
            ->assertHasNoFormComponentActionErrors();
        $this->assertSame($reversedKeys, array_keys(data_get($component->get('data'), $scorePath)));
        $this->assertSame($storedSchema, $form->fresh()->schema);
    }

    public function test_reactive_score_rows_persist_only_on_save_with_their_order_and_values(): void
    {
        $this->actingAs(User::factory()->create());
        $form = $this->reactiveScoringForm();
        $component = Livewire::test(EditForm::class, ['record' => $form->getRouteKey()]);
        $fields = $component->get('data')['schema']['fields'];
        $fieldKey = array_key_first($fields);
        $optionKey = array_key_first($fields[$fieldKey]['options']);
        $scorePath = "schema.fields.{$fieldKey}.options.{$optionKey}.scores";

        $component
            ->callFormComponentAction($scorePath, 'add')
            ->assertHasNoFormComponentActionErrors();
        $scores = data_get($component->get('data'), $scorePath);
        $scoreKeys = array_keys($scores);
        $newScoreKey = array_key_last($scores);
        $component
            ->set("data.{$scorePath}.{$newScoreKey}.key", 'c')
            ->set("data.{$scorePath}.{$newScoreKey}.score", 4);

        $reversedKeys = array_reverse($scoreKeys);
        $component
            ->callFormComponentAction($scorePath, 'reorder', arguments: ['items' => $reversedKeys])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(
            ['c' => 4, 'b' => 5, 'a' => 6],
            $form->fresh()->schema['fields'][0]['options'][0]['scores'],
        );

        $reopened = Livewire::test(EditForm::class, ['record' => $form->getRouteKey()]);
        $reopenedFields = $reopened->get('data')['schema']['fields'];
        $reopenedFieldKey = array_key_first($reopenedFields);
        $reopenedOptionKey = array_key_first($reopenedFields[$reopenedFieldKey]['options']);
        $reopenedScores = data_get(
            $reopened->get('data'),
            "schema.fields.{$reopenedFieldKey}.options.{$reopenedOptionKey}.scores",
        );

        $this->assertSame(['c', 'b', 'a'], array_column(array_values($reopenedScores), 'key'));
        $this->assertSame([4, 5, 6], array_column(array_values($reopenedScores), 'score'));
    }

    public function test_option_image_picker_uses_the_global_cms_modal_layer(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(EditForm::class, ['record' => $this->calculatorForm()->getRouteKey()])
            ->assertOk()
            ->assertSeeHtml('x-teleport="body"')
            ->assertSeeHtml('class="cms-modal-layer')
            ->assertSeeHtml('class="cms-modal-backdrop')
            ->assertSeeHtml('class="cms-modal-panel');
    }

    public function test_calculator_editor_preserves_hidden_keys_when_labels_and_scores_change(): void
    {
        $this->actingAs(User::factory()->create());
        $form = $this->calculatorForm();
        $component = Livewire::test(EditForm::class, ['record' => $form->getRouteKey()]);
        $data = $component->get('data');
        $recommendationItem = array_key_first($data['schema']['calculator']['recommendations']);
        $fieldItem = array_key_first($data['schema']['fields']);
        $optionItem = array_key_first($data['schema']['fields'][$fieldItem]['options']);
        $scoreItem = array_key_first($data['schema']['fields'][$fieldItem]['options'][$optionItem]['scores']);

        $this->assertSame('prefabricated', $data['schema']['calculator']['recommendations'][$recommendationItem]['key']);
        $this->assertSame('prefabricated', $data['schema']['fields'][$fieldItem]['options'][$optionItem]['scores'][$scoreItem]['key']);

        $component
            ->set("data.schema.calculator.recommendations.{$recommendationItem}.label", 'پیش‌ساخته سبک')
            ->set("data.schema.fields.{$fieldItem}.options.{$optionItem}.scores.{$scoreItem}.score", 7)
            ->call('save')
            ->assertHasNoFormErrors();

        $schema = $form->fresh()->schema;

        $this->assertSame('پیش‌ساخته سبک', $schema['calculator']['recommendations']['prefabricated']);
        $this->assertSame(7, $schema['fields'][0]['options'][0]['scores']['prefabricated']);
        $this->assertArrayNotHasKey('پیش‌ساخته سبک', $schema['calculator']['recommendations']);
    }

    public function test_save_boundary_generates_collision_safe_result_keys_without_changing_schema_shape(): void
    {
        $data = FormResource::prepareSchemaForStorage([
            'type' => 'calculator',
            'schema' => [
                'fields' => [],
                'calculator' => ['recommendations' => [
                    ['label' => 'Premium Plan'],
                    ['label' => 'Premium Plan'],
                    ['label' => '✨'],
                ]],
            ],
        ]);

        $this->assertSame([
            'premium_plan' => 'Premium Plan',
            'premium_plan_2' => 'Premium Plan',
            'result' => '✨',
        ], $data['schema']['calculator']['recommendations']);
    }

    public function test_adding_result_generates_hidden_key_from_initial_title(): void
    {
        $this->actingAs(User::factory()->create());
        $form = $this->calculatorForm();
        $path = 'schema.calculator.recommendations';
        $component = Livewire::test(EditForm::class, ['record' => $form->getRouteKey()])
            ->callFormComponentAction($path, 'add')
            ->assertHasNoFormComponentActionErrors();
        $recommendations = $component->get('data')['schema']['calculator']['recommendations'];
        $newItem = array_key_last($recommendations);

        $component
            ->set("data.{$path}.{$newItem}.label", 'Premium Plan')
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Premium Plan', $form->fresh()->schema['calculator']['recommendations']['premium_plan']);
    }

    public function test_choice_fields_render_manage_action_and_focused_choices_drawer(): void
    {
        $this->actingAs(User::factory()->create());
        $form = $this->choiceForm();

        Livewire::test(EditForm::class, ['record' => $form->getRouteKey()])
            ->assertOk()
            ->assertSee('مدیریت گزینه‌ها')
            ->assertSee('ویرایش انتخاب‌ها')
            ->assertSee('افزودن گزینه')
            ->assertSeeHtml('class="form-builder-choices-drawer"')
            ->assertSeeHtml('class="form-builder-choice-row"')
            ->assertSeeHtml('x-sortable-item=');
    }

    public function test_choice_actions_edit_the_existing_repeater_state_and_persist_unchanged_schema(): void
    {
        $this->actingAs(User::factory()->create());
        $form = $this->choiceForm();
        $component = Livewire::test(EditForm::class, ['record' => $form->getRouteKey()]);
        $fields = $component->get('data')['schema']['fields'];
        $fieldKey = array_key_first($fields);
        $optionKey = array_key_first($fields[$fieldKey]['options']);
        $optionsPath = "schema.fields.{$fieldKey}.options";

        $component
            ->set("data.{$optionsPath}.{$optionKey}.label", 'عنوان ویرایش‌شده')
            ->callFormComponentAction($optionsPath, 'add')
            ->assertHasNoFormComponentActionErrors();

        $options = $component->get('data')['schema']['fields'][$fieldKey]['options'];
        $newOptionKey = array_key_last($options);

        $this->assertSame('گزینه جدید', $options[$newOptionKey]['label']);
        $this->assertMatchesRegularExpression(
            '/^option_[0-7][0-9a-hjkmnp-tv-z]{25}$/',
            $options[$newOptionKey]['value'],
        );

        $reversedKeys = array_reverse(array_keys($options));
        $component->callFormComponentAction($optionsPath, 'reorder', arguments: ['items' => $reversedKeys]);
        $this->assertSame($reversedKeys, array_keys($component->get('data')['schema']['fields'][$fieldKey]['options']));

        $component
            ->callFormComponentAction($optionsPath, 'delete', arguments: ['item' => $newOptionKey])
            ->call('save')
            ->assertHasNoFormErrors();

        $savedOptions = $form->fresh()->schema['fields'][0]['options'];

        $this->assertCount(2, $savedOptions);
        $this->assertSame('عنوان ویرایش‌شده', collect($savedOptions)->firstWhere('value', 'first')['label']);
    }

    public function test_radio_choice_editor_hides_value_and_preserves_it_when_label_changes(): void
    {
        $this->actingAs(User::factory()->create());
        $form = $this->radioForm();
        $component = Livewire::test(EditForm::class, ['record' => $form->getRouteKey()])
            ->assertSee('عنوان گزینه')
            ->assertDontSee('مقدار گزینه');
        $fields = $component->get('data')['schema']['fields'];
        $fieldKey = array_key_first($fields);
        $optionKey = array_key_first($fields[$fieldKey]['options']);
        $originalValue = $fields[$fieldKey]['options'][$optionKey]['value'];

        $component
            ->set("data.schema.fields.{$fieldKey}.options.{$optionKey}.label", 'عنوان تازه')
            ->call('save')
            ->assertHasNoFormErrors();

        $savedOption = $form->fresh()->schema['fields'][0]['options'][0];
        $this->assertSame('عنوان تازه', $savedOption['label']);
        $this->assertSame($originalValue, $savedOption['value']);

        $form->update(['status' => 'published']);
        $this->post(route('forms.submit', $form->slug), [
            'contact_method' => $originalValue,
        ])->assertRedirect();
        $submission = FormSubmission::query()->sole();
        $this->assertSame($originalValue, $submission->payload['contact_method']);
        $this->assertSame(
            'عنوان تازه',
            data_get($submission->payload, '_answer_snapshot.0.display_value'),
        );
    }

    public function test_select_choice_editor_hides_value_and_preserves_legacy_value_for_submission(): void
    {
        $this->actingAs(User::factory()->create());
        $form = $this->choiceForm();
        $component = Livewire::test(EditForm::class, ['record' => $form->getRouteKey()])
            ->assertSee('عنوان گزینه')
            ->assertDontSee('مقدار گزینه');
        $fields = $component->get('data')['schema']['fields'];
        $fieldKey = array_key_first($fields);
        $optionKey = array_key_first($fields[$fieldKey]['options']);
        $originalValue = $fields[$fieldKey]['options'][$optionKey]['value'];

        $component
            ->set("data.schema.fields.{$fieldKey}.options.{$optionKey}.label", 'عنوان جدید Select')
            ->call('save')
            ->assertHasNoFormErrors();

        $savedOption = $form->fresh()->schema['fields'][0]['options'][0];
        $this->assertSame('عنوان جدید Select', $savedOption['label']);
        $this->assertSame($originalValue, $savedOption['value']);

        $form->update(['status' => 'published']);
        $this->post(route('forms.submit', $form->slug), [
            'choice' => $originalValue,
        ])->assertRedirect();

        $submission = FormSubmission::query()->sole();
        $this->assertSame($originalValue, $submission->payload['choice']);
        $this->assertSame(
            'عنوان جدید Select',
            data_get($submission->payload, '_answer_snapshot.0.display_value'),
        );
    }

    public function test_new_select_option_receives_one_stable_internal_value(): void
    {
        $this->actingAs(User::factory()->create());
        $form = $this->choiceForm();
        $component = Livewire::test(EditForm::class, ['record' => $form->getRouteKey()]);
        $fieldKey = array_key_first($component->get('data')['schema']['fields']);
        $optionsPath = "schema.fields.{$fieldKey}.options";

        $component->callFormComponentAction($optionsPath, 'add')
            ->assertHasNoFormComponentActionErrors();
        $options = $component->get('data')['schema']['fields'][$fieldKey]['options'];
        $newOptionKey = array_key_last($options);
        $internalValue = $options[$newOptionKey]['value'];

        $this->assertMatchesRegularExpression('/^option_[0-7][0-9a-hjkmnp-tv-z]{25}$/', $internalValue);

        $component
            ->set("data.{$optionsPath}.{$newOptionKey}.label", 'گزینه Select جدید')
            ->call('save')
            ->assertHasNoFormErrors();

        $savedOption = collect($form->fresh()->schema['fields'][0]['options'])
            ->firstWhere('value', $internalValue);
        $this->assertSame('گزینه Select جدید', $savedOption['label']);
        $this->assertSame($internalValue, $savedOption['value']);
    }

    public function test_new_radio_option_receives_stable_internal_value(): void
    {
        $this->actingAs(User::factory()->create());
        $form = $this->radioForm();
        $component = Livewire::test(EditForm::class, ['record' => $form->getRouteKey()]);
        $fieldKey = array_key_first($component->get('data')['schema']['fields']);
        $optionsPath = "schema.fields.{$fieldKey}.options";

        $component->callFormComponentAction($optionsPath, 'add')
            ->assertHasNoFormComponentActionErrors();
        $options = $component->get('data')['schema']['fields'][$fieldKey]['options'];
        $newOptionKey = array_key_last($options);
        $internalValue = $options[$newOptionKey]['value'];

        $this->assertMatchesRegularExpression('/^option_[0-7][0-9a-hjkmnp-tv-z]{25}$/', $internalValue);

        $component
            ->set("data.{$optionsPath}.{$newOptionKey}.label", 'گزینه افزوده‌شده')
            ->call('save')
            ->assertHasNoFormErrors();

        $savedOption = collect($form->fresh()->schema['fields'][0]['options'])
            ->firstWhere('value', $internalValue);
        $this->assertSame('گزینه افزوده‌شده', $savedOption['label']);
        $this->assertSame($internalValue, $savedOption['value']);
    }

    public function test_new_image_choice_field_creates_hidden_unique_option_values(): void
    {
        $this->actingAs(User::factory()->create());
        $component = Livewire::test(CreateForm::class)
            ->callFormComponentAction('schema.fields', 'add', arguments: ['fieldType' => 'image_choice'])
            ->assertHasNoFormComponentActionErrors()
            ->assertSee('تصویر گزینه')
            ->assertDontSee('مقدار گزینه');
        $fields = $component->get('data')['schema']['fields'];
        $fieldKey = array_key_last($fields);
        $optionsPath = "schema.fields.{$fieldKey}.options";

        $component
            ->callFormComponentAction($optionsPath, 'add')
            ->callFormComponentAction($optionsPath, 'add')
            ->assertHasNoFormComponentActionErrors();

        $options = array_values($component->get('data')['schema']['fields'][$fieldKey]['options']);
        $values = array_column($options, 'value');

        $this->assertNotEmpty($values);
        $this->assertCount(count($values), array_unique($values));
        foreach ($values as $value) {
            $this->assertMatchesRegularExpression('/^option_[0-7][0-9a-hjkmnp-tv-z]{25}$/', $value);
        }
    }

    public function test_image_choice_uses_hidden_unique_internal_values_and_preserves_them_during_edits(): void
    {
        $this->actingAs(User::factory()->create());
        $form = $this->imageChoiceForm();
        $component = Livewire::test(EditForm::class, ['record' => $form->getRouteKey()])
            ->assertSee('عنوان گزینه')
            ->assertSee('تصویر گزینه')
            ->assertDontSee('مقدار گزینه');
        $fieldKey = array_key_first($component->get('data')['schema']['fields']);
        $optionsPath = "schema.fields.{$fieldKey}.options";
        $originalOptions = $component->get('data')['schema']['fields'][$fieldKey]['options'];
        $originalOptionKey = array_key_first($originalOptions);
        $originalValue = $originalOptions[$originalOptionKey]['value'];

        $component
            ->set("data.{$optionsPath}.{$originalOptionKey}.label", 'سازه سبک ویرایش‌شده')
            ->set("data.{$optionsPath}.{$originalOptionKey}.image", '/media/updated-lsf.webp')
            ->callFormComponentAction($optionsPath, 'add')
            ->callFormComponentAction($optionsPath, 'add')
            ->assertHasNoFormComponentActionErrors();

        $options = $component->get('data')['schema']['fields'][$fieldKey]['options'];
        $newOptionKeys = array_slice(array_keys($options), -2);
        $newValues = array_map(fn (string $key): string => $options[$key]['value'], $newOptionKeys);

        $this->assertCount(count($options), array_unique(array_column(array_values($options), 'value')));
        foreach ($newValues as $value) {
            $this->assertMatchesRegularExpression('/^option_[0-7][0-9a-hjkmnp-tv-z]{25}$/', $value);
        }

        $reversedKeys = array_reverse(array_keys($options));
        $deletedOptionKey = $newOptionKeys[1];
        $expectedValues = collect($reversedKeys)
            ->reject(fn (string $key): bool => $key === $deletedOptionKey)
            ->map(fn (string $key): string => $options[$key]['value'])
            ->values()
            ->all();
        $component
            ->callFormComponentAction($optionsPath, 'reorder', arguments: ['items' => $reversedKeys])
            ->callFormComponentAction($optionsPath, 'delete', arguments: ['item' => $deletedOptionKey])
            ->call('save')
            ->assertHasNoFormErrors();

        $savedOptions = $form->fresh()->schema['fields'][0]['options'];
        $savedOriginal = collect($savedOptions)->firstWhere('value', $originalValue);

        $this->assertSame('سازه سبک ویرایش‌شده', $savedOriginal['label']);
        $this->assertSame('/media/updated-lsf.webp', $savedOriginal['image']);
        $this->assertSame($originalValue, $savedOriginal['value']);
        $this->assertSame($expectedValues, array_column($savedOptions, 'value'));
        $this->assertNotContains($options[$deletedOptionKey]['value'], array_column($savedOptions, 'value'));
    }

    public function test_image_choice_legacy_values_and_missing_values_are_normalized_without_rewriting_existing_values(): void
    {
        $data = FormResource::prepareSchemaForEditor([
            'type' => 'normal',
            'schema' => ['fields' => [[
                'key' => 'building_method',
                'label' => 'روش ساخت',
                'type' => 'image_choice',
                'options' => [
                    ['value' => 'legacy-lsf', 'label' => 'LSF', 'image' => '/media/lsf.webp'],
                    ['label' => 'بتن', 'image' => '/media/concrete.webp'],
                    ['label' => 'فولاد', 'image' => '/media/steel.webp'],
                ],
            ]]],
        ]);
        $options = $data['schema']['fields'][0]['options'];
        $values = array_column($options, 'value');

        $this->assertSame('legacy-lsf', $values[0]);
        $this->assertNotSame('', $values[1]);
        $this->assertNotSame('', $values[2]);
        $this->assertCount(3, array_unique($values));

        $stored = FormResource::prepareSchemaForStorage($data);
        $this->assertSame($values, array_column($stored['schema']['fields'][0]['options'], 'value'));
    }

    public function test_image_choice_submission_validates_internal_value_and_snapshots_the_label(): void
    {
        $form = $this->imageChoiceForm();
        $form->update(['status' => 'published']);
        $validValue = $form->schema['fields'][0]['options'][0]['value'];
        $url = route('forms.show', $form->slug);

        $this->from($url)
            ->post(route('forms.submit', $form->slug), ['building_method' => 'tampered'])
            ->assertRedirect($url)
            ->assertSessionHasErrors('building_method');
        $this->assertDatabaseCount('form_submissions', 0);

        $this->from($url)
            ->post(route('forms.submit', $form->slug), ['building_method' => $validValue])
            ->assertRedirect($url)
            ->assertSessionHasNoErrors();

        $submission = FormSubmission::query()->sole();
        $this->assertSame($validValue, $submission->payload['building_method']);
        $this->assertSame(
            'سازه سبک',
            data_get($submission->payload, '_answer_snapshot.0.display_value'),
        );
    }

    private function form(array $overrides = []): Form
    {
        return Form::query()->create(array_replace_recursive([
            'name' => 'Builder Test',
            'slug' => 'builder-test',
            'status' => 'draft',
            'display_mode' => 'page',
            'type' => 'normal',
            'schema_version' => 2,
            'schema' => ['fields' => [
                ['name' => 'name', 'label' => 'نام', 'type' => 'text', 'required' => true],
                ['name' => 'execution', 'label' => 'مرحله دوم: مشخصات اجرایی', 'type' => 'page'],
                ['name' => 'phone', 'label' => 'تلفن', 'type' => 'tel', 'required' => false],
            ]],
            'settings' => ['submit_label' => 'ارسال'],
        ], $overrides));
    }

    private function choiceForm(): Form
    {
        $form = $this->form();
        $form->update(['schema' => ['fields' => [[
            'name' => 'choice',
            'label' => 'انتخاب کنید',
            'type' => 'select',
            'required' => true,
            'options' => [
                ['value' => 'first', 'label' => 'گزینه اول'],
                ['value' => 'second', 'label' => 'گزینه دوم'],
            ],
        ]]]]);

        return $form->fresh();
    }

    private function radioForm(): Form
    {
        $form = $this->form();
        $form->update(['schema' => ['fields' => [[
            'name' => 'contact_method',
            'label' => 'روش تماس',
            'type' => 'radio',
            'required' => true,
            'options' => [
                ['value' => 'phone', 'label' => 'تلفن'],
                ['value' => 'email', 'label' => 'ایمیل'],
            ],
        ]]]]);

        return $form->fresh();
    }

    private function imageChoiceForm(): Form
    {
        $form = $this->form();
        $form->update(['schema' => ['fields' => [[
            'key' => 'building_method',
            'label' => 'روش ساخت',
            'type' => 'image_choice',
            'required' => true,
            'options' => [
                ['value' => 'legacy-lsf', 'label' => 'سازه سبک', 'image' => '/media/lsf.webp'],
                ['value' => 'legacy-concrete', 'label' => 'بتن', 'image' => '/media/concrete.webp'],
            ],
        ]]]]);

        return $form->fresh();
    }

    private function reactiveScoringForm(): Form
    {
        return $this->form([
            'type' => 'calculator',
            'calculator_identifier' => 'reactive_scoring_editor_v1',
            'schema' => [
                'fields' => [[
                    'key' => 'priority',
                    'label' => 'اولویت',
                    'type' => 'radio',
                    'required' => true,
                    'layout' => ['span' => 12],
                    'options' => [[
                        'value' => 'selected',
                        'label' => 'گزینه',
                        'scores' => ['a' => 6, 'b' => 5],
                    ]],
                ]],
                'calculator' => ['recommendations' => [
                    'a' => 'نتیجه A',
                    'b' => 'نتیجه B',
                    'c' => 'نتیجه C',
                ]],
            ],
        ]);
    }

    private function calculatorForm(): Form
    {
        return $this->form([
            'type' => 'calculator',
            'calculator_identifier' => 'editor_test_v1',
            'schema' => [
                'fields' => [[
                    'name' => 'method',
                    'label' => 'روش اجرا',
                    'type' => 'radio_card',
                    'required' => true,
                    'options' => [[
                        'value' => 'prefab',
                        'label' => 'پیش ساخته',
                        'scores' => ['prefabricated' => 3],
                    ]],
                ]],
                'calculator' => ['recommendations' => [
                    'prefabricated' => 'پیش ساخته',
                ]],
            ],
        ]);
    }
}
