<?php

namespace Tests\Feature;

use App\Filament\Resources\FormResource;
use App\Filament\Resources\FormResource\Pages\EditForm;
use App\Models\Form;
use App\Models\User;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CalculatorEligibilityFormBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_unsaved_clones_are_distinct_base_fields_and_comparison_changes_are_row_scoped(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $form = $this->form();
        $component = Livewire::test(EditForm::class, ['record' => $form->getRouteKey()]);
        $originalKey = array_key_first($component->get('data.schema.fields'));
        $component->callFormComponentAction('schema.fields', 'clone', arguments: ['item' => $originalKey]);
        $fields = $component->get('data.schema.fields');
        $cloneKey = array_key_last($fields);
        $original = $fields[$originalKey];
        $clone = $fields[$cloneKey];
        $cloneOptionKey = array_key_first($clone['options']);
        $component->set("data.schema.fields.{$cloneKey}.label", 'Transport question')
            ->set("data.schema.fields.{$cloneKey}.options.{$cloneOptionKey}.label", 'Transport option');
        $rule = [
            'field_id' => $original['field_id'], 'operator' => 'equals',
            'option_id' => array_values($original['options'])[0]['option_id'],
            'profiles' => ['masonry'], 'reason' => 'Existing rule',
        ];
        $component->set('data.schema.calculator.eligibility_rules', ['first' => $rule, 'second' => $rule]);
        $basePath = 'data.schema.calculator.eligibility_rules.first';
        $select = fn (string $path): Select => collect($component->instance()->form->getFlatComponents(withHidden: true))
            ->first(fn ($field): bool => $field instanceof Select && $field->getStatePath() === $path);

        $this->assertCount(2, $select($basePath.'.field_id')->getOptions());
        $this->assertNotSame($original['field_id'], $clone['field_id']);
        $this->assertNotSame($rule['option_id'], $clone['options'][$cloneOptionKey]['option_id']);
        $this->assertSame('villa', $clone['options'][$cloneOptionKey]['value']);
        $component->set($basePath.'.field_id', $clone['field_id'])
            ->assertSet($basePath.'.option_id', null)
            ->assertSet('data.schema.calculator.eligibility_rules.second.option_id', $rule['option_id'])
            ->assertDispatched('filament-forms::select.refreshSelectedOptionLabel', statePath: $basePath.'.option_id');
        $this->assertSame([
            $clone['options'][$cloneOptionKey]['option_id'] => 'Transport option',
        ], $select($basePath.'.option_id')->getOptions());
        $this->assertCount(2, $form->fresh()->schema['fields']); // No implicit save while editing.
        $component->set($basePath.'.option_id', $clone['options'][$cloneOptionKey]['option_id'])
            ->call('save')->assertHasNoFormErrors();
        $stored = $form->fresh()->schema['calculator']['eligibility_rules'];
        $this->assertSame($clone['field_id'], $stored[0]['field_id']);
        $this->assertSame($clone['options'][$cloneOptionKey]['option_id'], $stored[0]['option_id']);
        $this->assertSame($rule['option_id'], $stored[1]['option_id']);
    }

    public function test_base_fields_include_current_comparable_types_and_skip_structural_or_empty_choices(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $form = $this->form();
        $schema = $form->schema;
        foreach (['select', 'image_choice', 'radio_card', 'checkbox', 'number', 'page', 'step', 'radio'] as $index => $type) {
            $schema['fields'][] = [
                'key' => 'question_'.$index, 'type' => $type, 'label' => 'Question '.$index,
                'options' => $index < 4 ? [['value' => 'internal_'.$index, 'label' => 'Friendly '.$index]] : [],
            ];
        }
        $form->update(['schema' => $schema]);
        $component = Livewire::test(EditForm::class, ['record' => $form->getRouteKey()])
            ->set('data.schema.calculator.eligibility_rules', ['new' => ['field_id' => self::id('AV')]]);
        $fields = $component->get('data.schema.fields');
        $select = collect($component->instance()->form->getFlatComponents(withHidden: true))
            ->first(fn ($field): bool => $field instanceof Select && $field->getStatePath() === 'data.schema.calculator.eligibility_rules.new.field_id');
        $this->assertCount(6, $select->getOptions()); // Original radio, four choice types, and numeric comparisons.
        $this->assertNotContains('Question 5', $select->getOptions());
        $this->assertNotContains('Question 6', $select->getOptions());
        $this->assertNotContains('Question 7', $select->getOptions());
        $questionKey = array_keys($fields)[2];
        $component->set("data.schema.fields.{$questionKey}.label", 'Updated unsaved question');
        $select = collect($component->instance()->form->getFlatComponents(withHidden: true))
            ->first(fn ($field): bool => $field instanceof Select && $field->getStatePath() === 'data.schema.calculator.eligibility_rules.new.field_id');
        $this->assertContains('Updated unsaved question', $select->getOptions());
        $this->assertSame('Question 0', $form->fresh()->schema['fields'][2]['label']);
    }

    public function test_admin_can_create_reorder_and_edit_rules_with_stable_references(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $form = $this->form();
        $component = Livewire::test(EditForm::class, ['record' => $form->getRouteKey()])
            ->assertSee('قوانین صلاحیت گزینه‌ها')
            ->assertSee('قوانین Hard Eligibility');
        $data = $component->get('data');
        $field = array_values($data['schema']['fields'])[0];
        $fieldId = $field['field_id'];
        $optionId = array_values($field['options'])[0]['option_id'];
        $firstRule = [
            'rule_id' => '01ARZ3NDEKTSV4RRFFQ69G5FB0',
            'field_id' => $fieldId,
            'operator' => 'equals',
            'option_id' => $optionId,
            'number_value' => null,
            'profiles' => ['masonry'],
            'effect' => 'exclude',
            'reason' => 'قانون اول',
        ];
        $secondRule = [
            ...$firstRule,
            'rule_id' => '01ARZ3NDEKTSV4RRFFQ69G5FB1',
            'profiles' => ['lsf'],
            'reason' => 'قانون دوم',
        ];

        $component
            ->set('data.schema.calculator.eligibility_rules', ['first' => $firstRule, 'second' => $secondRule])
            ->call('save')
            ->assertHasNoFormErrors();

        $stored = $form->fresh()->schema['calculator']['eligibility_rules'];
        $this->assertSame([self::id('B0'), self::id('B1')], array_column($stored, 'rule_id'));
        $this->assertSame([$fieldId, $fieldId], array_column($stored, 'field_id'));
        $this->assertSame([$optionId, $optionId], array_column($stored, 'option_id'));

        $component = Livewire::test(EditForm::class, ['record' => $form->getRouteKey()]);
        $rules = $component->get('data')['schema']['calculator']['eligibility_rules'];
        $keys = array_keys($rules);
        $rules[$keys[0]]['reason'] = 'قانون اول ویرایش‌شده';
        $rules = [$keys[1] => $rules[$keys[1]], $keys[0] => $rules[$keys[0]]];
        $component->set('data.schema.calculator.eligibility_rules', $rules)
            ->call('save')
            ->assertHasNoFormErrors();

        $stored = $form->fresh()->schema['calculator']['eligibility_rules'];
        $this->assertSame([self::id('B1'), self::id('B0')], array_column($stored, 'rule_id'));
        $this->assertSame('قانون اول ویرایش‌شده', $stored[1]['reason']);
    }

    public function test_storage_boundary_rejects_unsupported_fields_invalid_options_operators_and_profiles(): void
    {
        $form = $this->form();
        $data = FormResource::prepareSchemaForEditor($form->toArray());
        [$choice, $text] = array_values($data['schema']['fields']);
        data_set($data, 'schema.calculator.eligibility_rules', [
            [
                'rule_id' => self::id('B0'),
                'field_id' => $choice['field_id'],
                'operator' => 'contains',
                'option_id' => $choice['options'][0]['option_id'],
                'profiles' => ['masonry'],
                'reason' => 'عملگر نامعتبر',
            ],
            [
                'rule_id' => self::id('B1'),
                'field_id' => $text['field_id'],
                'operator' => 'equals',
                'option_id' => $choice['options'][0]['option_id'],
                'profiles' => ['masonry'],
                'reason' => 'فیلد نامعتبر',
            ],
            [
                'rule_id' => self::id('B2'),
                'field_id' => $choice['field_id'],
                'operator' => 'equals',
                'option_id' => self::id('B9'),
                'profiles' => ['unknown'],
                'reason' => 'Reference نامعتبر',
            ],
        ]);

        $stored = FormResource::prepareSchemaForStorage($data);

        $this->assertSame([], $stored['schema']['calculator']['eligibility_rules']);
    }

    private function form(): Form
    {
        return Form::query()->create([
            'name' => 'Eligibility Builder',
            'slug' => 'eligibility-builder',
            'status' => 'draft',
            'display_mode' => 'page',
            'type' => 'calculator',
            'calculator_identifier' => 'eligibility_builder_v1',
            'schema_version' => 2,
            'schema' => [
                'fields' => [
                    [
                        'field_id' => self::id('AV'),
                        'key' => 'building_type',
                        'label' => 'نوع ساختمان',
                        'type' => 'radio',
                        'required' => true,
                        'options' => [[
                            'option_id' => self::id('AW'),
                            'value' => 'villa',
                            'label' => 'ویلا',
                            'scores' => ['masonry' => 2, 'lsf' => 3],
                        ]],
                    ],
                    [
                        'field_id' => self::id('AX'),
                        'key' => 'notes',
                        'label' => 'متن آزاد غیرمجاز',
                        'type' => 'text',
                        'required' => false,
                    ],
                ],
                'calculator' => ['recommendations' => ['masonry' => 'بنایی', 'lsf' => 'LSF']],
            ],
            'settings' => [],
        ]);
    }

    private static function id(string $suffix): string
    {
        return '01ARZ3NDEKTSV4RRFFQ69G5F'.$suffix;
    }
}
