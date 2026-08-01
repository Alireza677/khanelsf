<?php

namespace Tests\Feature;

use App\Filament\Resources\FormResource;
use App\Filament\Resources\FormResource\Pages\CreateForm;
use App\Filament\Resources\FormResource\Pages\EditForm;
use App\Models\Form;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FormBuilderEditorTest extends TestCase
{
    use RefreshDatabase;

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
            ->assertDontSee('مدیریت گزینه‌ها')
            ->assertDontSee('امتیازها');
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
            ->assertSee('گزینه اول')
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
            ->assertSee('عنوان ویرایش‌شده')
            ->callFormComponentAction($optionsPath, 'add')
            ->assertHasNoFormComponentActionErrors();

        $options = $component->get('data')['schema']['fields'][$fieldKey]['options'];
        $newOptionKey = array_key_last($options);

        $this->assertSame('گزینه جدید', $options[$newOptionKey]['label']);
        $this->assertNull($options[$newOptionKey]['value']);

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
