<?php

namespace Tests\Unit;

use App\Filament\Resources\FormResource;
use App\Models\Form;
use App\Services\Calculators\CalculatorManager;
use App\Services\Calculators\CalculatorScoringSchema;
use App\Services\FormSchema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CalculatorScoringSchemaTest extends TestCase
{
    public function test_performance_matrix_completes_blank_cells_and_validates_effective_values(): void
    {
        $contract = app(CalculatorScoringSchema::class);
        $speed = $contract->newCriterion('سرعت اجرا');
        $cost = $contract->newCriterion('هزینه');
        $id = $speed['id'];
        $schema = ['calculator' => [
            'scoring_mode' => 'weighted', 'criteria' => [$speed, $cost],
            'recommendations' => ['steel' => 'سازه فولادی', 'concrete' => 'بتن'],
        ]];
        $zeros = array_fill_keys(['steel', 'concrete'], [$id => 0, $cost['id'] => 0]);
        $this->assertSame($zeros, $contract->normalize($schema)['calculator']['criterion_scores']);
        foreach ([null, '', [], ['steel' => null], ['steel' => [$id => null]], ['steel' => [$id => '']]] as $matrix) {
            $schema['calculator']['criterion_scores'] = $matrix;
            $this->assertSame($zeros, $contract->normalize($schema)['calculator']['criterion_scores']);
        }
        foreach ([0, '0', 5, '5', 2.5] as $value) {
            $schema['calculator']['criterion_scores'] = ['steel' => [$id => $value]];
            $expected = $zeros;
            $expected['steel'][$id] = $value + 0;
            $normalized = $contract->normalize($schema);
            $this->assertSame($expected, $normalized['calculator']['criterion_scores']);
            $this->assertSame($normalized, $contract->normalize($normalized));
        }
        foreach ([5.1, 6, -1, 'invalid', false, [], INF] as $value) {
            $schema['calculator']['criterion_scores'] = ['steel' => [$id => $value]];
            try {
                $contract->normalize($schema);
                $this->fail('Accepted an invalid performance score.');
            } catch (ValidationException $exception) {
                $this->assertSame(
                    ['امتیاز «سرعت اجرا» برای «سازه فولادی» باید عددی بین ۰ تا ۵ باشد.'],
                    $exception->errors()["schema.calculator.criterion_scores.steel.{$id}"],
                );
            }
        }
    }

    public function test_weighted_contract_survives_editor_round_trip_rename_and_deletion(): void
    {
        $contract = app(CalculatorScoringSchema::class);
        $criterion = $contract->newCriterion('سرعت اجرا');
        $id = $criterion['id'];
        $data = ['type' => 'calculator', 'schema' => [
            'calculator' => [
                'scoring_mode' => 'weighted',
                'criteria' => [$criterion, ['label' => 'هزینه']],
                'recommendations' => ['lsf' => 'سازه سبک'],
                'criterion_scores' => ['lsf' => [strtolower($id) => '5']],
            ],
            'fields' => [[
                'key' => 'priority', 'label' => 'اولویت', 'type' => 'radio',
                'options' => [[
                    'value' => 'speed', 'label' => 'سرعت',
                    'scores' => ['lsf' => -1.5], 'criterion_weights' => [$id => '8.5'],
                ]],
            ]],
        ]];
        $stored = FormResource::prepareSchemaForStorage($data);
        $this->assertSame($stored, FormResource::prepareSchemaForStorage($stored));
        $this->assertSame($stored, FormResource::prepareSchemaForStorage(FormResource::prepareSchemaForEditor($stored)));
        $this->assertSame(0, $stored['schema']['calculator']['criteria'][1]['base_weight']);
        $this->assertSame([$id => 5, $stored['schema']['calculator']['criteria'][1]['id'] => 0], $stored['schema']['calculator']['criterion_scores']['lsf']);
        $this->assertSame([$id => 8.5], app(FormSchema::class)->fields(new Form($stored))[0]['options'][0]['criterion_weights']);

        $stored['schema']['calculator']['criteria'][0]['label'] = 'سرعت جدید';
        $renamed = FormResource::prepareSchemaForStorage($stored);
        $this->assertSame($stored, $renamed);
        $stored['schema']['calculator']['criteria'] = [];
        $deleted = FormResource::prepareSchemaForStorage($stored);
        $this->assertSame([], $deleted['schema']['calculator']['criterion_scores']['lsf']);
        $this->assertSame([], $deleted['schema']['fields'][0]['options'][0]['criterion_weights']);
        $this->assertSame(['lsf' => -1.5], $deleted['schema']['fields'][0]['options'][0]['scores']);
    }

    public function test_invalid_weighted_values_are_rejected_before_normalization(): void
    {
        $contract = app(CalculatorScoringSchema::class);
        $criterion = $contract->newCriterion('سرعت');
        $id = $criterion['id'];
        $base = ['calculator' => ['scoring_mode' => 'weighted', 'criteria' => [$criterion], 'recommendations' => ['lsf' => 'سبک']]];
        $cases = [
            ['calculator.scoring_mode', 'other'],
            ['calculator.scoring_mode', null],
            ['calculator.criteria', null],
            ['calculator.criteria.0.id', 'bad.id'],
            ['calculator.criteria.0.id', '01ARZ3NDEKTSV4RRFFQ69G5FAV'."\n"],
            ['calculator.criteria.0.label', ' '],
            ['calculator.criteria.1', $criterion],
            ['calculator.criteria.0.base_weight', -1],
            ['calculator.criteria.0.base_weight', 11],
            ['calculator.criteria.0.base_weight', true],
            ['calculator.criteria.0.base_weight', INF],
            ['calculator.criteria.0.base_weight', '1e999'],
            ['calculator.criterion_scores', ['lsf' => [$id => 5.1]]],
            ['calculator.criterion_scores', ['lsf' => ['عنوان' => 2]]],
            ['calculator.criterion_scores', ['invalid.result' => [$id => 2]]],
            ['calculator.criterion_scores', ['lsf' => [$id => 1, strtolower($id) => 2]]],
            ['calculator.criterion_scores', false],
            ['calculator.criterion_scores', ['lsf' => 2]],
            ['fields.0.options.0.criterion_weights', [$id => 10.1]],
            ['fields.0.options.0.criterion_weights', [$id => null]],
            ['fields.0.options.0.criterion_weights', ['bad' => 1]],
            ['fields.0.options.0.criterion_weights', [$id => 1, strtolower($id) => 2]],
        ];
        foreach ($cases as [$path, $value]) {
            $schema = $base;
            data_set($schema, $path, $value);
            try {
                $contract->normalize($schema);
                $this->fail("Accepted invalid value at {$path}");
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
                $this->assertMatchesRegularExpression('/[\x{0600}-\x{06FF}]/u', $exception->validator->errors()->first());
            }
        }
    }

    public function test_legacy_simple_data_is_untouched_and_weighted_cannot_use_the_simple_engine(): void
    {
        $contract = app(CalculatorScoringSchema::class);
        $schema = ['calculator' => ['recommendations' => ['lsf' => 'سبک']], 'fields' => [[
            'key' => 'choice', 'label' => 'انتخاب', 'type' => 'radio',
            'options' => [['value' => 'one', 'label' => 'یک', 'scores' => ['lsf' => -2.5]]],
        ]]];
        $this->assertSame('simple', $contract->scoringMode($schema));
        $this->assertSame($schema, $contract->normalize($schema));
        $form = new Form(['type' => 'calculator', 'slug' => 'example', 'schema' => $schema]);
        $manager = app(CalculatorManager::class);
        $this->assertSame(['lsf' => -2.5], $manager->calculate($form, ['choice' => 'one'])->scores);
        $schema['calculator']['scoring_mode'] = 'simple';
        $form->schema = $schema;
        $this->assertSame(['lsf' => -2.5], $manager->calculate($form, ['choice' => 'one'])->scores);
        $schema['calculator']['scoring_mode'] = 'weighted';
        $form->schema = $schema;
        $weighted = $manager->calculate($form, ['choice' => 'one'])->toArray();
        $this->assertTrue($weighted['no_score']);
        $this->assertSame(['lsf' => '0'], $weighted['scores']);
    }
}
