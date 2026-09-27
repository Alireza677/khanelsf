<?php

namespace Tests\Unit;

use App\Models\Form;
use App\Services\Calculators\CalculationResultRows;
use App\Services\Calculators\CalculatorManager;
use Tests\TestCase;

class WeightedCalculatorTest extends TestCase
{
    private const SPEED = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
    private const QUAKE = '01ARZ3NDEKTSV4RRFFQ69G5FAW';

    public function test_example_percentages_ties_and_factor_breakdown_use_actual_contributions(): void
    {
        $form = $this->form();
        $result = app(CalculatorManager::class)->calculate($form, ['speed' => 'fast', 'needs' => ['safe'], 'scores' => ['other' => 9999]])->toArray();
        $this->assertSame(['lsf' => '90', 'other' => '62', 'tied' => '90'], $result['scores']);
        $this->assertSame(['lsf' => '100.00', 'other' => '68.89', 'tied' => '100.00'], $result['suitability_percentages']);
        $this->assertSame('90', $result['max_possible_score']);
        $this->assertSame('lsf', $result['recommended_method']);
        $this->assertSame(['lsf', 'tied', 'other'], array_column($result['ranking'], 'key'));
        $this->assertSame(['زلزله', 'سرعت'], array_column($result['top_factors'], 'label'));
        $this->assertSame(['50', '40'], array_column($result['top_factors'], 'contribution'));
        $this->assertSame('اجرای سریع', $result['answer_labels']['speed']);
        $rows = app(CalculationResultRows::class)->fromSnapshot($result);
        $this->assertSame('۶۸٫۸۹٪', $rows[2]['suitability_label']);
        $this->assertSame('62', $rows[2]['raw_score']);
    }

    public function test_decimal_sums_base_weights_missing_values_and_zero_weight_state(): void
    {
        $form = $this->form();
        $schema = $form->schema;
        $schema['calculator']['criteria'][0]['base_weight'] = 0.1;
        $schema['fields'][0]['options'][0]['criterion_weights'] = [self::SPEED => 0.2];
        $schema['fields'][1]['options'][0]['criterion_weights'] = [self::QUAKE => 1e-7];
        $form->schema = $schema;
        $manager = app(CalculatorManager::class);
        $result = $manager->calculate($form, ['speed' => 'fast', 'needs' => ['safe']])->toArray();
        $this->assertSame('0.3', $result['criterion_weights'][self::SPEED]);
        $this->assertSame('1.5000005', $result['scores']['lsf']);
        $this->assertSame('0.0000001', $result['criterion_weights'][self::QUAKE]);
        $this->assertSame('100.00', $result['suitability_percentages']['lsf']);
        $schema['calculator']['criteria'][0]['base_weight'] = 0;
        $form->schema = $schema;
        $empty = $manager->calculate($form, [])->toArray();
        $this->assertTrue($empty['no_score']);
        $this->assertNull($empty['recommended_method']);
        $this->assertFalse($empty['no_eligible_recommendation']);
        $this->assertSame([null, null, null], array_values($empty['suitability_percentages']));
        $this->assertSame([null, null, null], array_column(app(CalculationResultRows::class)->fromSnapshot($empty), 'rank'));
        $schema['calculator']['criterion_scores'] = [];
        $form->schema = $schema;
        $missing = $manager->calculate($form, ['speed' => 'fast'])->toArray();
        $this->assertSame(['lsf' => '0', 'other' => '0', 'tied' => '0'], $missing['scores']);
        $this->assertSame('lsf', $missing['recommended_method']);
    }

    public function test_eligibility_excludes_a_higher_score_without_changing_its_score(): void
    {
        $form = $this->form();
        $schema = $form->schema;
        $schema['calculator']['eligibility_rules'] = [[
            'rule_id' => '01ARZ3NDEKTSV4RRFFQ69G5FB0',
            'field_id' => $schema['fields'][0]['field_id'], 'option_id' => $schema['fields'][0]['options'][0]['option_id'],
            'operator' => 'equals', 'profiles' => ['lsf', 'tied'], 'effect' => 'exclude', 'reason' => 'شرایط پروژه اجازه نمی‌دهد',
        ]];
        $form->schema = $schema;
        $result = app(CalculatorManager::class)->calculate($form, ['speed' => 'fast', 'needs' => ['safe']])->toArray();
        $this->assertSame('other', $result['recommended_method']);
        $this->assertSame('90', $result['scores']['lsf']);
        $this->assertSame('100.00', $result['suitability_percentages']['lsf']);
        $this->assertFalse($result['eligibility']['lsf']['eligible']);
        $this->assertNull($result['ranking'][1]['rank']);
        $schema['calculator']['eligibility_rules'][0]['profiles'][] = 'other';
        $form->schema = $schema;
        $none = app(CalculatorManager::class)->calculate($form, ['speed' => 'fast'])->toArray();
        $this->assertNull($none['recommended_method']);
        $this->assertTrue($none['no_eligible_recommendation']);
        $this->assertFalse($none['no_score']);
    }

    private function form(): Form
    {
        return new Form(['type' => 'calculator', 'slug' => 'weighted', 'schema' => [
            'calculator' => [
                'scoring_mode' => 'weighted',
                'criteria' => [['id' => self::SPEED, 'label' => 'سرعت'], ['id' => self::QUAKE, 'label' => 'زلزله']],
                'recommendations' => ['lsf' => 'سازه سبک', 'other' => 'فولادی', 'tied' => 'هم‌امتیاز'],
                'criterion_scores' => [
                    'lsf' => [self::SPEED => 5, self::QUAKE => 5],
                    'other' => [self::SPEED => 4, self::QUAKE => 3],
                    'tied' => [self::SPEED => 5, self::QUAKE => 5],
                ],
            ],
            'fields' => [
                ['field_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAX', 'key' => 'speed', 'label' => 'سرعت', 'type' => 'select', 'options' => [[
                    'option_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAY', 'value' => 'fast', 'label' => 'اجرای سریع', 'criterion_weights' => [self::SPEED => 8], 'scores' => ['other' => 999],
                ]]],
                ['key' => 'needs', 'label' => 'نیازها', 'type' => 'checkbox', 'options' => [[
                    'value' => 'safe', 'label' => 'مقاومت در برابر زلزله', 'criterion_weights' => [self::QUAKE => 10],
                ]]],
            ],
        ]]);
    }
}
