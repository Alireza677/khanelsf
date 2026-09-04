<?php

namespace Tests\Unit;

use App\Services\Calculators\CalculatorEligibilityRuleSchema;
use Tests\TestCase;

class CalculatorEligibilityRuleSchemaTest extends TestCase
{
    public function test_it_canonicalizes_valid_rules_and_rejects_invalid_references_operators_and_profiles(): void
    {
        $fields = $this->fields();
        $rules = [
            [
                'rule_id' => '01arz3ndektsv4rrffq69g5fb0',
                'field_id' => '01arz3ndektsv4rrffq69g5fav',
                'operator' => 'equals',
                'option_id' => '01arz3ndektsv4rrffq69g5faw',
                'profiles' => ['masonry', 'unknown', 'masonry', 'lsf'],
                'effect' => 'exclude',
                'reason' => '  دلیل معتبر  ',
            ],
            [
                'rule_id' => '01ARZ3NDEKTSV4RRFFQ69G5FB1',
                'field_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAX',
                'operator' => 'greater_than_or_equal',
                'number_value' => '۴',
                'profiles' => ['masonry'],
                'effect' => 'exclude',
                'reason' => 'عدد فارسی خام مجاز نیست',
            ],
            ['field_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV', 'operator' => 'contains', 'option_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAW', 'profiles' => ['masonry'], 'reason' => 'عملگر نامعتبر'],
            ['field_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAY', 'operator' => 'equals', 'option_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAW', 'profiles' => ['masonry'], 'reason' => 'فیلد unsupported'],
            ['field_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV', 'operator' => 'equals', 'option_id' => '01ARZ3NDEKTSV4RRFFQ69G5FB9', 'profiles' => ['masonry'], 'reason' => 'گزینه نامعتبر'],
            ['field_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV', 'operator' => 'equals', 'option_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAW', 'profiles' => ['unknown'], 'reason' => 'Profile نامعتبر'],
        ];

        $normalized = app(CalculatorEligibilityRuleSchema::class)->normalize(
            $fields,
            ['masonry' => 'بنایی', 'lsf' => 'LSF'],
            $rules,
        );

        $this->assertCount(1, $normalized);
        $this->assertSame('01ARZ3NDEKTSV4RRFFQ69G5FB0', $normalized[0]['rule_id']);
        $this->assertSame(['masonry', 'lsf'], $normalized[0]['profiles']);
        $this->assertSame('دلیل معتبر', $normalized[0]['reason']);
        $this->assertSame('exclude', $normalized[0]['effect']);
    }

    public function test_stable_rule_field_and_option_references_survive_label_changes_and_reordering(): void
    {
        $rule = [[
            'rule_id' => '01ARZ3NDEKTSV4RRFFQ69G5FB0',
            'field_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'operator' => 'equals',
            'option_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAW',
            'profiles' => ['masonry'],
            'effect' => 'exclude',
            'reason' => 'دلیل',
        ]];
        $fields = $this->fields();
        $before = app(CalculatorEligibilityRuleSchema::class)->normalize($fields, ['masonry' => 'بنایی'], $rule);
        $fields[0]['label'] = 'عنوان جدید';
        $fields[0]['options'][0]['label'] = 'گزینه جدید';
        $fields[0]['options'] = array_reverse($fields[0]['options']);
        $after = app(CalculatorEligibilityRuleSchema::class)->normalize(array_reverse($fields), ['masonry' => 'بنایی جدید'], $rule);

        $this->assertSame($before, $after);
    }

    private function fields(): array
    {
        return [
            [
                'field_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
                'key' => 'choice',
                'label' => 'انتخاب',
                'type' => 'radio',
                'options' => [
                    ['option_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAW', 'value' => 'one', 'label' => 'یک'],
                    ['option_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAZ', 'value' => 'two', 'label' => 'دو'],
                ],
            ],
            ['field_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAX', 'key' => 'number', 'label' => 'عدد', 'type' => 'number'],
            ['field_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAY', 'key' => 'text', 'label' => 'متن', 'type' => 'text'],
        ];
    }
}
