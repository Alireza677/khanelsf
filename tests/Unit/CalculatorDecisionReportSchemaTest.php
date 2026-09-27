<?php

namespace Tests\Unit;

use App\Services\Calculators\CalculatorScoringSchema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CalculatorDecisionReportSchemaTest extends TestCase
{
    private const CRITERION = '01ARZ3NDEKTSV4RRFFQ69G5FAV';

    private const DELETED_CRITERION = '01ARZ3NDEKTSV4RRFFQ69G5FAW';

    public function test_absent_config_stays_absent_and_present_config_defaults_to_disabled(): void
    {
        $contract = app(CalculatorScoringSchema::class);
        $schema = $this->schema();
        $legacy = $contract->normalize($schema);
        $this->assertArrayNotHasKey('decision_report', $legacy['calculator']);

        $schema['calculator']['decision_report'] = [];
        $normalized = $contract->normalize($schema);
        $this->assertSame([
            'enabled' => false, 'top_factors_count' => 3, 'explanations' => [],
        ], $normalized['calculator']['decision_report']);
        unset($normalized['calculator']['decision_report']);
        $this->assertSame($legacy, $normalized);
    }

    public function test_explanations_use_canonical_ids_and_survive_label_changes_and_normalization(): void
    {
        $contract = app(CalculatorScoringSchema::class);
        $schema = $this->schema();
        $schema['calculator']['decision_report'] = [
            'enabled' => true, 'top_factors_count' => 20,
            'explanations' => ['lsf' => [strtolower(self::CRITERION) => '  اجرای سریع‌تر  ']],
        ];
        $normalized = $contract->normalize($schema);
        $expected = [
            'enabled' => true, 'top_factors_count' => 20,
            'explanations' => ['lsf' => [self::CRITERION => 'اجرای سریع‌تر']],
        ];
        $this->assertSame($expected, $normalized['calculator']['decision_report']);
        $this->assertSame($normalized, $contract->normalize($normalized));
        $this->assertSame($schema['calculator']['criterion_scores'], $normalized['calculator']['criterion_scores']);
        $this->assertSame($schema['calculator']['criteria'], $normalized['calculator']['criteria']);

        $normalized['calculator']['criteria'][0]['label'] = 'عنوان جدید معیار';
        $normalized['calculator']['recommendations']['lsf'] = 'عنوان جدید نتیجه';
        $this->assertSame($expected, $contract->normalize($normalized)['calculator']['decision_report']);
    }

    public function test_deleted_references_and_empty_explanations_are_pruned(): void
    {
        $contract = app(CalculatorScoringSchema::class);
        $schema = $this->schema();
        $schema['calculator']['decision_report'] = ['explanations' => [
            'deleted_result' => [self::CRITERION => 'متن قدیمی'],
            'lsf' => [self::DELETED_CRITERION => 'معیار حذف‌شده', self::CRITERION => ' متن معتبر '],
        ]];
        $normalized = $contract->normalize($schema);
        $this->assertSame(['lsf' => [self::CRITERION => 'متن معتبر']], $normalized['calculator']['decision_report']['explanations']);

        foreach ([null, '', " \t\n "] as $text) {
            $schema['calculator']['decision_report']['explanations']['lsf'][self::CRITERION] = $text;
            $this->assertSame([], $contract->normalize($schema)['calculator']['decision_report']['explanations']);
        }

        $normalized['calculator']['criteria'] = [];
        $this->assertSame([], $contract->normalize($normalized)['calculator']['decision_report']['explanations']);
        $normalized = $contract->normalize($this->schema());
        $normalized['calculator']['decision_report'] = ['explanations' => ['lsf' => [self::CRITERION => 'متن']]];
        $normalized['calculator']['recommendations'] = [];
        $this->assertSame([], $contract->normalize($normalized)['calculator']['decision_report']['explanations']);

        $schema['calculator']['decision_report']['explanations'] = null;
        $this->assertSame([], $contract->normalize($schema)['calculator']['decision_report']['explanations']);
    }

    public function test_invalid_weighted_report_values_have_scoped_validation_errors(): void
    {
        $contract = app(CalculatorScoringSchema::class);
        $cases = [
            ['', null], ['', 'invalid'], ['', false],
            ['enabled', null], ['enabled', 1], ['enabled', 'true'],
            ['top_factors_count', 0], ['top_factors_count', -1], ['top_factors_count', 1.5],
            ['top_factors_count', 3.0], ['top_factors_count', '3'], ['top_factors_count', true],
            ['top_factors_count', null],
            ['explanations', 'invalid'], ['explanations.lsf', 'invalid'],
            ['explanations', ['عنوان نتیجه' => [self::CRITERION => 'متن']]],
            ['explanations.lsf', ['عنوان معیار' => 'متن']],
            ['explanations.lsf', [self::CRITERION => 'متن', strtolower(self::CRITERION) => 'تکراری']],
            ['explanations.lsf.'.self::CRITERION, 42],
            ['explanations.lsf.'.self::CRITERION, false],
            ['explanations.lsf.'.self::CRITERION, []],
        ];
        foreach ($cases as [$suffix, $value]) {
            $schema = $this->schema();
            $key = 'calculator.decision_report'.($suffix === '' ? '' : '.'.$suffix);
            data_set($schema, $key, $value);
            try {
                $contract->normalize($schema, 'data.schema');
                $this->fail("Accepted invalid report value at {$key}");
            } catch (ValidationException $exception) {
                $this->assertStringStartsWith('data.schema.calculator.decision_report', array_key_first($exception->errors()));
            }
        }
    }

    public function test_simple_and_implicit_simple_preserve_report_metadata_without_new_validation(): void
    {
        $contract = app(CalculatorScoringSchema::class);
        foreach ([null, 'simple'] as $mode) {
            $schema = ['calculator' => ['recommendations' => ['lsf' => 'سبک']]];
            if ($mode !== null) {
                $schema['calculator']['scoring_mode'] = $mode;
            }
            $this->assertSame($schema, $contract->normalize($schema));
            foreach ([null, 'invalid', ['enabled' => 'invalid'], ['enabled' => true, 'top_factors_count' => 0]] as $report) {
                $schema['calculator']['decision_report'] = $report;
                $this->assertSame($schema, $contract->normalize($schema));
            }
        }
    }

    private function schema(): array
    {
        return ['calculator' => [
            'scoring_mode' => 'weighted',
            'criteria' => [['id' => self::CRITERION, 'label' => 'سرعت', 'base_weight' => 2]],
            'recommendations' => ['lsf' => 'سبک'],
            'criterion_scores' => ['lsf' => [self::CRITERION => 4.5]],
        ]];
    }
}
