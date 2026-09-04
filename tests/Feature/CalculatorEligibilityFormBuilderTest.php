<?php

namespace Tests\Feature;

use App\Filament\Resources\FormResource;
use App\Filament\Resources\FormResource\Pages\EditForm;
use App\Models\Form;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CalculatorEligibilityFormBuilderTest extends TestCase
{
    use RefreshDatabase;

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
